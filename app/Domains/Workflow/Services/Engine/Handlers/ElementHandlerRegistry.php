<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine\Handlers;

use App\Domains\Workflow\Enums\ElementType;
use App\Domains\Workflow\Exceptions\WorkflowException;

class ElementHandlerRegistry
{
    /**
     * @var array<string, ElementHandlerInterface>
     */
    private array $handlers = [];

    public function register(string $elementType, ElementHandlerInterface $handler): void
    {
        $this->handlers[$elementType] = $handler;
    }

    public function get(string $elementType): ElementHandlerInterface
    {
        if (!isset($this->handlers[$elementType])) {
            throw WorkflowException::unsupportedElement($elementType);
        }
        return $this->handlers[$elementType];
    }

    public function has(string $elementType): bool
    {
        return isset($this->handlers[$elementType]);
    }

    /**
     * ثبت تمام handlerهای پیش‌فرض.
     */
    public function registerDefaults(): void
    {
        $this->register(ElementType::StartEvent->value, app(StartEventHandler::class));
        $this->register(ElementType::EndEvent->value, app(EndEventHandler::class));
        $this->register(ElementType::UserTask->value, app(UserTaskHandler::class));
        $this->register(ElementType::ServiceTask->value, app(ServiceTaskHandler::class));
        $this->register(ElementType::ManualTask->value, app(ManualTaskHandler::class));
        $this->register(ElementType::ReceiveTask->value, app(ReceiveTaskHandler::class));
        $this->register(ElementType::ExclusiveGateway->value, app(ExclusiveGatewayHandler::class));
        $this->register(ElementType::ParallelGateway->value, app(ParallelGatewayHandler::class));
        $this->register(ElementType::InclusiveGateway->value, app(InclusiveGatewayHandler::class));
        $this->register(ElementType::IntermediateCatchEvent->value, app(IntermediateCatchEventHandler::class));
    }
}
