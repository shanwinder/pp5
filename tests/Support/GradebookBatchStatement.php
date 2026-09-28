<?php
declare(strict_types=1);

/** Test-only execution observer, including failures after a real PDO mutation has executed. */
final class GradebookBatchStatement extends PDOStatement
{
    protected function __construct(private Closure $afterExecute) {}

    public function execute(?array $params = null): bool
    {
        $result = parent::execute($params);
        ($this->afterExecute)($this->queryString, $params);
        return $result;
    }
}
