<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Adapters;

use App\Domains\Integrations\Contracts\SsoDriverInterface;
use App\Domains\Integrations\DTOs\HealthCheckResult;
use App\Domains\Integrations\DTOs\SsoLoginResult;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * SamlGenericAdapter — adapter عمومی SAML 2.0.
 *
 * این adapter به یک کتابخانه SAML واقعی (مثل onelogin/php-saml یا
 * lightsaml/lightsaml) نیاز دارد که در composer.json پروژه اضافه شده باشد.
 * در این پیاده‌سازی، نقاط ادغام (extension points) را تعریف می‌کنیم.
 *
 * config مورد نیاز:
 * - sp_entity_id
 * - sp_acs_url (callback)
 * - sp_certificate / sp_private_key
 * - idp_entity_id
 * - idp_sso_url
 * - idp_slo_url
 * - idp_certificate
 * - attribute_mapping: { email: 'emailAddress', name: 'displayName', ... }
 */
class SamlGenericAdapter extends AbstractIntegrationAdapter implements SsoDriverInterface
{
    public function getName(): string
    {
        return 'SAML 2.0';
    }

    public function checkHealth(): HealthCheckResult
    {
        // بررسی اولیه: حداقل metadata می‌سازد یا خیر
        try {
            $required = ['sp_entity_id', 'sp_acs_url', 'idp_entity_id', 'idp_sso_url', 'idp_certificate'];
            $missing = [];
            foreach ($required as $key) {
                if (!$this->config($key)) {
                    $missing[] = $key;
                }
            }

            if (!empty($missing)) {
                return HealthCheckResult::degraded(
                    message: 'پیکربندی SAML ناقص است',
                    details: ['missing' => $missing],
                );
            }

            // یک ping ساده به IdP SSO URL
            $ch = curl_init($this->config('idp_sso_url'));
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $start = microtime(true);
            curl_exec($ch);
            $latency = (int) ((microtime(true) - $start) * 1000);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                return HealthCheckResult::down("IdP در دسترس نیست: {$err}");
            }

            return HealthCheckResult::healthy(
                message: 'پیکربندی صحیح و IdP در دسترس است',
                latencyMs: $latency,
            );
        } catch (\Throwable $e) {
            return HealthCheckResult::down('بررسی سلامت با خطا مواجه شد: ' . $e->getMessage());
        }
    }

    public function initiateLogin(?string $returnUrl = null): RedirectResponse
    {
        // در پیاده‌سازی واقعی:
        // - یک AuthnRequest XML ساخته می‌شود
        // - با base64 encode می‌شود
        // - با relayState (شامل returnUrl) به idp_sso_url ریدایرکت می‌شود

        $samlRequest = $this->buildAuthnRequest();
        $encoded = base64_encode($samlRequest);

        $url = $this->config('idp_sso_url') . '?' . http_build_query([
            'SAMLRequest' => $encoded,
            'RelayState' => $returnUrl ?? '/admin',
        ]);

        return new RedirectResponse($url);
    }

    public function handleCallback(array $request): SsoLoginResult
    {
        $samlResponse = $request['SAMLResponse'] ?? null;
        if (!$samlResponse) {
            throw new \InvalidArgumentException('SAMLResponse در درخواست موجود نیست.');
        }

        // پیاده‌سازی واقعی:
        // - decode و parse XML
        // - verification of signature with idp_certificate
        // - assertion validity check (NotBefore, NotOnOrAfter)
        // - extract attributes

        $xml = base64_decode($samlResponse);
        $assertion = $this->parseAssertion($xml);

        $mapping = $this->config('attribute_mapping', []);

        return new SsoLoginResult(
            sessionId: $assertion['session_index'] ?? bin2hex(random_bytes(16)),
            nameId: $assertion['name_id'],
            nameIdFormat: $assertion['name_id_format'] ?? null,
            email: $assertion['attributes'][$mapping['email'] ?? 'email'] ?? null,
            displayName: $assertion['attributes'][$mapping['name'] ?? 'displayName'] ?? null,
            attributes: $assertion['attributes'] ?? [],
            expiresAt: isset($assertion['not_on_or_after'])
                ? new \DateTimeImmutable($assertion['not_on_or_after'])
                : null,
        );
    }

    public function initiateLogout(string $sessionId, ?string $returnUrl = null): RedirectResponse
    {
        $sloUrl = $this->config('idp_slo_url');
        if (!$sloUrl) {
            return new RedirectResponse($returnUrl ?? '/login');
        }

        $logoutRequest = $this->buildLogoutRequest($sessionId);
        $url = $sloUrl . '?' . http_build_query([
            'SAMLRequest' => base64_encode($logoutRequest),
            'RelayState' => $returnUrl ?? '/login',
        ]);

        return new RedirectResponse($url);
    }

    public function getServiceProviderMetadata(): string
    {
        // برمی‌گرداند یک XML metadata برای ارائه به IdP
        $entityId = htmlspecialchars((string) $this->config('sp_entity_id'));
        $acsUrl = htmlspecialchars((string) $this->config('sp_acs_url'));

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
                     entityID="{$entityId}">
    <md:SPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <md:AssertionConsumerService
            Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
            Location="{$acsUrl}"
            index="0" />
    </md:SPSSODescriptor>
</md:EntityDescriptor>
XML;
    }

    // ──────── Helpers — در پیاده‌سازی production این‌ها به کتابخانه SAML واقعی تفویض می‌شوند ────────

    private function buildAuthnRequest(): string
    {
        $id = '_' . bin2hex(random_bytes(16));
        $issueInstant = gmdate('Y-m-d\TH:i:s\Z');
        $entityId = (string) $this->config('sp_entity_id');
        $acsUrl = (string) $this->config('sp_acs_url');
        $idpUrl = (string) $this->config('idp_sso_url');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                    ID="{$id}"
                    Version="2.0"
                    IssueInstant="{$issueInstant}"
                    Destination="{$idpUrl}"
                    AssertionConsumerServiceURL="{$acsUrl}"
                    ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">
    <saml:Issuer>{$entityId}</saml:Issuer>
</samlp:AuthnRequest>
XML;
    }

    private function buildLogoutRequest(string $sessionId): string
    {
        $id = '_' . bin2hex(random_bytes(16));
        $issueInstant = gmdate('Y-m-d\TH:i:s\Z');
        $entityId = (string) $this->config('sp_entity_id');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                     xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                     ID="{$id}"
                     Version="2.0"
                     IssueInstant="{$issueInstant}">
    <saml:Issuer>{$entityId}</saml:Issuer>
    <samlp:SessionIndex>{$sessionId}</samlp:SessionIndex>
</samlp:LogoutRequest>
XML;
    }

    private function parseAssertion(string $xml): array
    {
        // پیاده‌سازی production می‌تواند از DOMDocument و XPath استفاده کند یا onelogin/php-saml
        $dom = new \DOMDocument();
        @$dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');

        $nameId = $xpath->query('//saml:NameID')->item(0)?->nodeValue ?? '';
        $sessionIndex = $xpath->query('//saml:AuthnStatement/@SessionIndex')->item(0)?->nodeValue;
        $notOnOrAfter = $xpath->query('//saml:Conditions/@NotOnOrAfter')->item(0)?->nodeValue;

        $attributes = [];
        foreach ($xpath->query('//saml:Attribute') as $attr) {
            /** @var \DOMElement $attr */
            $name = $attr->getAttribute('Name');
            $values = [];
            foreach ($xpath->query('saml:AttributeValue', $attr) as $val) {
                $values[] = $val->nodeValue;
            }
            $attributes[$name] = count($values) === 1 ? $values[0] : $values;
        }

        return [
            'name_id' => $nameId,
            'session_index' => $sessionIndex,
            'not_on_or_after' => $notOnOrAfter,
            'attributes' => $attributes,
        ];
    }
}
