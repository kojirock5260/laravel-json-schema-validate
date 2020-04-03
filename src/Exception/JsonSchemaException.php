<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class JsonSchemaException extends HttpException
{
    /**
     * JsonSchemaException constructor.
     * @param string|null $message
     * @param \Throwable|null $previous
     * @param array $headers
     * @param int $code
     */
    public function __construct(?string $message = null, ?\Throwable $previous = null, array $headers = [], int $code = 0)
    {
        parent::__construct(400, $message, $previous, $headers, $code);
        $this->setMessage($this->getJsonErrorMessage());
    }

    /**
     * Get JsonErrorMessage.
     * @return string
     */
    public function getJsonErrorMessage(): string
    {
        $results = [];
        $messageList = unserialize($this->getMessage(), ['allowed_classes' => true]);
        foreach ($messageList as $v) {
            $results[] = $v['message'];
        }

        return implode(',', $results);
    }

    /**
     * Set ErrorMessage.
     * @param string $errorMessage
     */
    public function setMessage(string $errorMessage): void
    {
        $this->message = $errorMessage;
    }
}
