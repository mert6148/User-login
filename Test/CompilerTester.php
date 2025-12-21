<?php

class CompilerTestCase
{
    private array $errors = [];

    public function assertClassExists(string $class): void
    {
        if (!class_exists($class)) {
            $this->errors[] = "Class not found: {$class}";
        }
    }

    public function assertMethodExists(string $class, string $method): void
    {
        if (!method_exists($class, $method)) {
            $this->errors[] = "Method not found: {$class}::{$method}";
        }
    }

    public function assertCallable(callable $fn, string $label): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->errors[] = "{$label} failed: " . $e->getMessage();
        }
    }

    public function report(): void
    {
        if (empty($this->errors)) {
            echo "[✔] Compiler Test Passed\n";
            return;
        }

        echo "[✘] Compiler Test Failed\n";
        foreach ($this->errors as $error) {
            echo " - {$error}\n";
        }
    }
}
