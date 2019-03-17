<?php

declare(strict_types=1);

namespace Kojirock\Exception;

class JsonSchemaException extends \Exception
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function render(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        return \Illuminate\Support\Facades\Response::json([
            'errorMessage' => $this->getJsonErrorMessage(),
        ], 400);
    }

    /**
     * Get JsonErrorMessage.
     * @return array
     */
    public function getJsonErrorMessage(): array
    {
        $results     = [];
        $messageList = unserialize($this->getMessage());

        foreach ($messageList as $v) {
            $results[] = $v['message'];
        }
        return $results;
    }
}
