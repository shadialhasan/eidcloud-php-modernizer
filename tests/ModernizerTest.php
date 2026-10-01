<?php

declare(strict_types=1);

namespace EidCloud\PhpModernizer\Tests;

use EidCloud\PhpModernizer\Modernizer;
use EidCloud\PhpModernizer\Transformers\MysqlToPdoTransformer;
use EidCloud\PhpModernizer\Transformers\ConstructorTransformer;
use EidCloud\PhpModernizer\Transformers\PropertyPromotionTransformer;
use EidCloud\PhpModernizer\Transformers\EregToPregTransformer;
use EidCloud\PhpModernizer\Transformers\EachToForeachTransformer;
use EidCloud\PhpModernizer\Transformers\NullCoalescingTransformer;
use EidCloud\PhpModernizer\Transformers\MatchExpressionTransformer;
use EidCloud\PhpModernizer\Diff\UnifiedDiffGenerator;

class ModernizerTest
{
    private int $passes = 0;
    private int $failures = 0;

    public function runAll(): bool
    {
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (str_starts_with($method, 'test')) {
                echo "Running \033[36m{$method}\033[0m... ";
                try {
                    $this->{$method}();
                    echo "\033[32mPASS\033[0m\n";
                    $this->passes++;
                } catch (\Throwable $e) {
                    echo "\033[31mFAIL\033[0m: " . $e->getMessage() . "\n";
                    $this->failures++;
                }
            }
        }

        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Results: {$this->passes} passed, {$this->failures} failed\n";
        echo str_repeat('=', 50) . "\n";

        return $this->failures === 0;
    }

    private function assert(bool $condition, string $message = 'Assertion failed'): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    private function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            $msg = $message ?: "Failed asserting that text contains '{$needle}'";
            throw new \RuntimeException($msg);
        }
    }

    private function assertNotContains(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            $msg = $message ?: "Failed asserting that text does not contain '{$needle}'";
            throw new \RuntimeException($msg);
        }
    }

    public function testMysqlToPdo(): void
    {
        $transformer = new MysqlToPdoTransformer();
        $legacy = '<?php $c = mysql_connect("localhost", "root", "pass"); mysql_select_db("db", $c); $q = mysql_query("SELECT 1", $c); $r = mysql_fetch_assoc($q);';
        $modern = $transformer->transform($legacy);

        $this->assertContains('new \\PDO', $modern);
        $this->assertContains('$c->query', $modern);
        $this->assertContains('$q->fetch(\\PDO::FETCH_ASSOC)', $modern);
        $this->assertNotContains('mysql_connect', $modern);
        $this->assertNotContains('mysql_query', $modern);
    }

    public function testLegacyConstructor(): void
    {
        $transformer = new ConstructorTransformer();
        $legacy = '<?php class ItemService { function ItemService($id) { $this->id = $id; } }';
        $modern = $transformer->transform($legacy);

        $this->assertContains('function __construct', $modern);
        $this->assertNotContains('function ItemService', $modern);
    }

    public function testEregToPreg(): void
    {
        $transformer = new EregToPregTransformer();
        $legacy = '<?php $valid = ereg("^[a-z]+$", $str); $rep = ereg_replace("foo", "bar", $text); $parts = split(",", $csv);';
        $modern = $transformer->transform($legacy);

        $this->assertContains('preg_match', $modern);
        $this->assertContains('preg_replace', $modern);
        $this->assertContains("explode(',', \$csv)", $modern);
        $this->assertNotContains('ereg(', $modern);
        $this->assertNotContains('ereg_replace(', $modern);
    }

    public function testEachToForeach(): void
    {
        $transformer = new EachToForeachTransformer();
        $legacy = '<?php while (list($k, $v) = each($arr)) { echo $k; }';
        $modern = $transformer->transform($legacy);

        $this->assertContains('foreach ($arr as $k => $v)', $modern);
        $this->assertNotContains('each(', $modern);
    }

    public function testNullCoalescing(): void
    {
        $transformer = new NullCoalescingTransformer();
        $legacy = '<?php $val = isset($_GET["id"]) ? $_GET["id"] : 0;';
        $modern = $transformer->transform($legacy);

        $this->assertContains('$_GET["id"] ?? 0', $modern);
        $this->assertNotContains('isset(', $modern);
    }

    public function testMatchExpression(): void
    {
        $transformer = new MatchExpressionTransformer();
        $legacy = '<?php function test($x) { switch ($x) { case 1: return "one"; break; case 2: return "two"; break; default: return "other"; break; } }';
        $modern = $transformer->transform($legacy);

        $this->assertContains('return match ($x) {', $modern);
        $this->assertContains('1 => "one",', $modern);
        $this->assertContains('default => "other",', $modern);
    }

    public function testPropertyPromotion(): void
    {
        $transformer = new PropertyPromotionTransformer();
        $legacy = '<?php class User { var $name; var $email; public function __construct($name, $email) { $this->name = $name; $this->email = $email; } }';
        $modern = $transformer->transform($legacy);

        $this->assertContains('public function __construct(', $modern);
        $this->assertContains('public $name', $modern);
        $this->assertContains('public $email', $modern);
        $this->assertNotContains('var $name', $modern);
    }

    public function testFullModernizerWorkflow(): void
    {
        $modernizer = new Modernizer();
        $fixturePath = __DIR__ . '/Fixtures/sample_legacy.php';
        $result = $modernizer->modernizeFile($fixturePath);

        $this->assert($result['hasChanged'], 'Fixture should be modernized');
        $this->assert($result['isValidSyntax'], 'Modernized fixture must pass syntax check');
        $this->assertContains('new \\PDO', $result['modernized']);
        $this->assertContains('__construct', $result['modernized']);
        $this->assertContains('preg_match', $result['modernized']);
        $this->assertContains('??', $result['modernized']);
        $this->assertContains('match ($status)', $result['modernized']);
    }

    public function testDiffGenerator(): void
    {
        $diffGen = new UnifiedDiffGenerator();
        $orig = "line 1\nline 2\nline 3";
        $mod = "line 1\nmodified line 2\nline 3";
        $diff = $diffGen->generate($orig, $mod, 'test.php');

        $this->assertContains('--- a/test.php', $diff);
        $this->assertContains('+++ b/test.php', $diff);
        $this->assertContains('-line 2', $diff);
        $this->assertContains('+modified line 2', $diff);
    }
}
