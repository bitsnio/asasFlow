<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Controller;

use Bitsnio\AsasFlow\Foundation\Http\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControllerActionSynchronizer
{
    /**
     * Inspects an existing controller file content and appends any missing action methods
     * without overwriting developer-modified existing methods.
     *
     * @return array{0: string, 1: array<int, string>} [updatedContent, addedMethodNames]
     */
    public function sync(string $document, array $expectedMethods): array
    {
        $missingSnippets = [];
        $addedMethodNames = [];
        $needsRequestImport = false;
        $needsJsonResponseImport = false;
        $needsResponseTrait = false;

        foreach ($expectedMethods as $methodName => $codeSnippet) {
            if (!$this->methodExistsInClass($document, $methodName)) {
                $missingSnippets[] = $codeSnippet;
                $addedMethodNames[] = $methodName;

                if (str_contains($codeSnippet, 'Request $request')) {
                    $needsRequestImport = true;
                }

                if (str_contains($codeSnippet, 'JsonResponse')) {
                    $needsJsonResponseImport = true;
                }

                if (
                    str_contains($codeSnippet, '$this->paginatedResponse(')
                    || str_contains($codeSnippet, '$this->successResponse(')
                    || str_contains($codeSnippet, '$this->createdResponse(')
                    || str_contains($codeSnippet, '$this->errorResponse(')
                    || str_contains($codeSnippet, '$this->notImplementedResponse(')
                ) {
                    $needsResponseTrait = true;
                }
            }
        }

        if (empty($missingSnippets)) {
            return [$document, []];
        }

        if ($needsRequestImport) {
            $document = $this->ensureImportExists($document, Request::class);
        }

        if ($needsJsonResponseImport) {
            $document = $this->ensureImportExists($document, JsonResponse::class);
        }

        if ($needsResponseTrait) {
            $document = $this->ensureImportExists($document, JsonResponseTrait::class);
            $document = $this->ensureTraitUsedInClass($document, class_basename(JsonResponseTrait::class));
        }

        $lastBracePos = strrpos($document, '}');

        if ($lastBracePos === false) {
            return [$document, []];
        }

        $beforeBrace = rtrim(substr($document, 0, $lastBracePos));
        $afterBrace = substr($document, $lastBracePos);

        $injectedBlock = implode(PHP_EOL . PHP_EOL, $missingSnippets);

        $updatedDocument = $beforeBrace
            . PHP_EOL . PHP_EOL
            . $injectedBlock
            . PHP_EOL
            . $afterBrace;

        return [$updatedDocument, $addedMethodNames];
    }

    protected function methodExistsInClass(string $document, string $methodName): bool
    {
        $pattern = '/\bfunction\s+' . preg_quote($methodName, '/') . '\s*\(/';

        return preg_match($pattern, $document) === 1;
    }

    protected function ensureImportExists(string $document, string $fqcn): string
    {
        $importPattern = '/^use\s+' . preg_quote($fqcn, '/') . '\s*;/m';

        if (preg_match($importPattern, $document) === 1) {
            return $document;
        }

        $useStatement = "use {$fqcn};";
        $classPos = strpos($document, 'class ');
        $header = $classPos !== false ? substr($document, 0, $classPos) : $document;

        if (preg_match_all('/^use\s+[^\n;]+;/m', $header, $matches, PREG_OFFSET_CAPTURE)) {
            $lastMatch = end($matches[0]);
            $insertPos = $lastMatch[1] + strlen($lastMatch[0]);

            return substr($document, 0, $insertPos)
                . PHP_EOL
                . $useStatement
                . substr($document, $insertPos);
        }

        if (preg_match('/^namespace\s+[^\n;]+;/m', $header, $match, PREG_OFFSET_CAPTURE)) {
            $insertPos = $match[0][1] + strlen($match[0][0]);

            return substr($document, 0, $insertPos)
                . PHP_EOL . PHP_EOL
                . $useStatement
                . substr($document, $insertPos);
        }

        return $document;
    }

    protected function ensureTraitUsedInClass(string $document, string $traitShortName): string
    {
        $classPos = strpos($document, 'class ');
        if ($classPos === false) {
            return $document;
        }

        $classBody = substr($document, $classPos);
        $traitPattern = '/^\s*use\s+[^;]*\b' . preg_quote($traitShortName, '/') . '\b[^;]*;/m';

        if (preg_match($traitPattern, $classBody) === 1) {
            return $document;
        }

        $openBracePos = strpos($document, '{', $classPos);
        if ($openBracePos === false) {
            return $document;
        }

        return substr($document, 0, $openBracePos + 1)
            . PHP_EOL
            . "    use {$traitShortName};"
            . substr($document, $openBracePos + 1);
    }
}
