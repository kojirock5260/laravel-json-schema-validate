<?php

declare(strict_types=1);

namespace Kojirock\JsonSchemaValidate\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class JsonSchemaException extends HttpException
{
    /**
     * JsonSchemaException constructor.
     * @param null|string     $message
     * @param null|\Exception $previous
     * @param int             $code
     * @param array           $headers
     */
    public function __construct(string $message = null, \Exception $previous = null, int $code = 0, array $headers = [])
    {
        parent::__construct(400, $message, $previous, $headers, $code);
        $errorMessage = $this->getJsonErrorMessage();
        $this->setMessage($errorMessage);
    }

    /**
     * Get JsonErrorMessage.
     * @return string
     */
    public function getJsonErrorMessage(): string
    {
        $results     = [];
        $messageList = unserialize($this->getMessage());

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
