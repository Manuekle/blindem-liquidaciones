<?php

namespace App\Services\Ai;

/**
 * A provider that resolves one assistant task.
 *
 * `$task` names the job (for example `contract.extract`), `$instructions` is the prompt a language
 * model receives, `$input` is plain JSON-serialisable data and `$schema` is the JSON Schema the
 * answer must follow. Implementations return the decoded structured answer.
 */
interface AiClient
{
    public function run(string $task, string $instructions, array $input, array $schema = []): array;

    /** Short identifier shown to people, e.g. "Simulado". */
    public function label(): string;
}
