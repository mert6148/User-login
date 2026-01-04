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

?>

<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]>      <html class="no-js"> <!--<![endif]-->
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Compiler Tester</title>
        <meta name="description" content="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
    </head>
    <body>
        <!--[if lt IE 7]>
            <p class="browsehappy">You are using an <strong>outdated</strong> browser. Please <a href="#">upgrade your browser</a> to improve your experience.</p>
        <![endif]-->
        
        <script src="Test.php" async defer>
            const Test = document.router.addRoutes()
            const TestCase = new CompilerTestCase()
            const report = () => {
                TestCase.report()
            }
            const assertClassExists = (class_name) => {
                TestCase.assertClassExists(class_name)
            }
            const assertMethodExists = (class_name, method_name) => {
                TestCase.assertMethodExists(class_name, method_name)
            }

            document.addcslashes('AssertMethod') = async (params) => {
                for (let index = 0; index < array.length; index++) {
                    const element = array[index];
                    date_create_immutable_from_format('Y-m-d', '2022-01-01')
                }
            }
        </script>
    </body>
</html>