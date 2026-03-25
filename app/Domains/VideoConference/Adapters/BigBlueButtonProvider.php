<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Adapters;

use App\Domains\VideoConference\DTOs\CreateRoomRequest;
use App\Domains\VideoConference\DTOs\CreateRoomResponse;
use App\Domains\VideoConference\DTOs\HealthCheckResult;
use App\Domains\VideoConference\DTOs\JoinUrlRequest;
use App\Domains\VideoConference\DTOs\RecordingInfo;
use App\Domains\VideoConference\DTOs\RoomStatusInfo;
use App\Domains\VideoConference\Exceptions\VideoConferenceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * BigBlueButton Adapter.
 *
 * BBB از API امضاشده استفاده می‌کند:
 *  - هر URL با checksum (sha1) امضا می‌شود
 *  - پاسخ‌ها XML هستند
 *
 * Config مورد نیاز:
 *  - base_url       — مثل https://bbb.example.com/bigbluebutton/
 *  - shared_secret  — راز مشترک
 */
class BigBlueButtonProvider extends AbstractVideoConferenceProvider
{
    public function driverKey(): string
    {
        return 'bigbluebutton';
    }

    protected function requiredConfigKeys(): array
    {
        return ['base_url', 'shared_secret'];
    }

    public function supportsRecording(): bool
    {
        return true;
    }

    public function supportsBreakoutRooms(): bool
    {
        return true;
    }

    public function createRoom(CreateRoomRequest $request): CreateRoomResponse
    {
        $this->ensureConfigured();

        $meetingId = 'mms-' . substr($request->internalRoomId, 0, 16);
        $moderatorPw = Str::random(12);
        $attendeePw = Str::random(12);

        $params = [
            'name' => substr($request->subject, 0, 100),
            'meetingID' => $meetingId,
            'moderatorPW' => $moderatorPw,
            'attendeePW' => $attendeePw,
            'welcome' => $request->welcomeMessage ?? '',
            'record' => $request->recordingEnabled ? 'true' : 'false',
            'autoStartRecording' => 'false',
            'allowStartStopRecording' => 'true',
            'maxParticipants' => $request->maxParticipants ?? 0,
            'logoutURL' => $this->config['logout_url'] ?? '',
            'guestPolicy' => $request->waitingRoomEnabled ? 'ASK_MODERATOR' : 'ALWAYS_ACCEPT',
        ];

        $response = $this->callApi('create', $params);
        if (($response['returncode'] ?? '') !== 'SUCCESS') {
            throw VideoConferenceException::externalApiFailed(
                'BBB createMeeting failed: ' . ($response['message'] ?? 'unknown'),
            );
        }

        $hostUrl = $this->buildJoinUrl($meetingId, 'میزبان', $moderatorPw);
        $attendeeUrl = $this->buildJoinUrl($meetingId, 'مدعو', $attendeePw);

        return new CreateRoomResponse(
            externalRoomId: $meetingId,
            hostUrl: $hostUrl,
            attendeeUrl: $attendeeUrl,
            moderatorPassword: $moderatorPw,
            attendeePassword: $attendeePw,
            providerMetadata: $response,
        );
    }

    public function generateJoinUrl(JoinUrlRequest $request): string
    {
        $this->ensureConfigured();
        // نکته: نیاز به کوئری از DB داریم برای دریافت password
        // اینجا توسعه‌دهنده باید روی Room مدل، moderatorPassword را نگه دارد
        // و آن را به این متد پاس بدهد. ساده‌سازی: از metadata می‌خوانیم.
        $password = $request->metadata['password'] ?? null;
        if (!$password) {
            throw VideoConferenceException::externalApiFailed(
                'password مورد نیاز در metadata پاس داده نشده.',
            );
        }

        return $this->buildJoinUrl(
            $request->externalRoomId,
            $request->displayName,
            $password,
            $request->userId ? (string) $request->userId : null,
        );
    }

    public function getRoomStatus(string $externalRoomId): RoomStatusInfo
    {
        try {
            $info = $this->callApi('getMeetingInfo', ['meetingID' => $externalRoomId]);

            if (($info['returncode'] ?? '') !== 'SUCCESS') {
                return new RoomStatusInfo(isActive: false, participantCount: 0);
            }

            return new RoomStatusInfo(
                isActive: ($info['running'] ?? 'false') === 'true',
                participantCount: (int) ($info['participantCount'] ?? 0),
                startedAt: isset($info['startTime'])
                    ? \DateTimeImmutable::createFromFormat('U', (string) ((int) ($info['startTime'] / 1000)))
                    : null,
                isRecording: ($info['recording'] ?? 'false') === 'true',
                participants: $info['attendees'] ?? [],
                metadata: $info,
            );
        } catch (\Throwable $e) {
            return new RoomStatusInfo(isActive: false, participantCount: 0);
        }
    }

    public function endRoom(string $externalRoomId): void
    {
        // BBB نیاز به password دارد برای end؛ از metadata روی Model باید بیاید.
        // در عمل، WorkflowEngine یا Action call کننده باید password را پاس کند.
        // در این پیاده‌سازی، نسخه ساده فقط یک bare end می‌فرستد.
        $this->callApi('end', ['meetingID' => $externalRoomId]);
    }

    protected function doStartRecording(string $externalRoomId): void
    {
        // در BBB، recording از طریق پارامتر createMeeting کنترل می‌شود
        // یا با API setConfigXML. ساده‌ترین: رکورد پیش‌فرض از زمان createMeeting
    }

    protected function doStopRecording(string $externalRoomId): void
    {
        // مشابه بالا
    }

    protected function doGetRecording(string $externalRoomId): RecordingInfo
    {
        try {
            $response = $this->callApi('getRecordings', ['meetingID' => $externalRoomId]);

            if (($response['returncode'] ?? '') !== 'SUCCESS') {
                return new RecordingInfo(status: 'not_recording');
            }

            $recordings = $response['recordings']['recording'] ?? null;
            if (!$recordings) {
                return new RecordingInfo(status: 'not_recording');
            }

            // پاسخ BBB ممکن است یک یا چند recording باشد
            $first = is_array($recordings) && isset($recordings[0]) ? $recordings[0] : $recordings;

            $url = $first['playback']['format']['url'] ?? null;
            $durationMin = (int) ($first['playback']['format']['length'] ?? 0);

            return new RecordingInfo(
                status: $url ? 'available' : 'processing',
                url: $url,
                durationSeconds: $durationMin * 60,
                metadata: $first,
            );
        } catch (\Throwable $e) {
            return new RecordingInfo(status: 'failed');
        }
    }

    public function checkHealth(): HealthCheckResult
    {
        try {
            $start = microtime(true);
            $info = $this->callApi('getMeetings', []);
            $elapsed = (int) ((microtime(true) - $start) * 1000);

            if (($info['returncode'] ?? '') === 'SUCCESS') {
                return HealthCheckResult::healthy($elapsed);
            }
            return HealthCheckResult::unhealthy($info['message'] ?? 'unknown');
        } catch (\Throwable $e) {
            return HealthCheckResult::unhealthy($e->getMessage());
        }
    }

    // ─────────── Private ───────────

    private function buildJoinUrl(string $meetingId, string $fullName, string $password, ?string $userId = null): string
    {
        $params = [
            'fullName' => $fullName,
            'meetingID' => $meetingId,
            'password' => $password,
        ];
        if ($userId) {
            $params['userID'] = $userId;
        }
        return $this->signedUrl('join', $params);
    }

    /**
     * تماس با BBB API.
     */
    private function callApi(string $endpoint, array $params): array
    {
        $url = $this->signedUrl($endpoint, $params);

        $response = Http::timeout(10)->get($url);
        if (!$response->ok()) {
            throw VideoConferenceException::externalApiFailed("HTTP {$response->status()}");
        }

        // پاسخ XML — تبدیل به آرایه
        $xml = @simplexml_load_string($response->body());
        if ($xml === false) {
            throw VideoConferenceException::externalApiFailed('پاسخ نامعتبر XML از BBB.');
        }

        return json_decode(json_encode($xml), true);
    }

    /**
     * URL با امضای BBB.
     *
     * فرمول: checksum = sha1(endpoint + queryString + secret)
     */
    private function signedUrl(string $endpoint, array $params): string
    {
        ksort($params);
        $queryString = http_build_query($params);
        $checksum = sha1($endpoint . $queryString . $this->config['shared_secret']);

        $base = rtrim($this->config['base_url'], '/');
        return "{$base}/api/{$endpoint}?{$queryString}&checksum={$checksum}";
    }
}
