<?php

namespace LeoVie\C2Spark\Tests;

use LeoVie\C2Spark\FuncDefTranspiler;
use LeoVie\C2Spark\Transpiler;
use PHPUnit\Framework\TestCase;

class TranspilerTest extends TestCase
{
    private const TESTDATA_DIR = __DIR__ . '/../../../testdata';

    private Transpiler $transpiler;

    protected function setUp(): void
    {
        $this->transpiler = new Transpiler();
    }

    public function testTranspileId(): void
    {
        $data = $this->loadFixture('nodetypes/ID/01.json');
        $expected = ['value' => 'y'];
        $actual = $this->transpiler->transpileId($data);

        self::assertEquals($expected, $actual);
    }

    public function testTranspileConstant(): void
    {
        $data = $this->loadFixture('nodetypes/Constant/01.json');
        $expected = ['value' => '42', 'type' => 'Integer'];
        $actual = $this->transpiler->transpileConstant($data);

        self::assertEquals($expected, $actual);
    }

    /** @dataProvider binaryOpProvider */
    public function testTranspileBinaryOp(string $fixturePath, array $expected): void
    {
        $data = $this->loadFixture($fixturePath);
        $actual = $this->transpiler->transpileBinaryOp($data);

        self::assertEquals($expected, $actual);
    }

    public function binaryOpProvider(): array
    {
        return [
            [
                'nodetypes/BinaryOp/01.json',
                ['value' => '(x / 42)'],
            ],
            [
                'nodetypes/BinaryOp/02.json',
                ['value' => '(i < 50)'],
            ],
        ];
    }

    /** @dataProvider compoundProvider */
    public function testTranspileCompound(string $fixturePath, string $expected): void
    {
        $data = $this->loadFixture($fixturePath);
        $this->transpiler->transpileCompound($data);

        $actual = $this->transpiler->compounds[0];

        self::assertEquals($expected, $actual);
    }

    public function compoundProvider(): array
    {
        return [
            [
                'nodetypes/Compound/01.json',
                'result := ((x / 42) * y)',
            ],
            [
                'nodetypes/Compound/02.json',
                'printf("%d", i)',
            ],
        ];
    }

    public function testTranspileReturn(): void
    {
        $data = $this->loadFixture('nodetypes/Return/01.json');
        $actual = $this->transpiler->transpileReturn($data);

        self::assertEquals(['value' => 'return result'], $actual);
    }

    public function testTranspileParamList(): void
    {
        $data = $this->loadFixture('nodetypes/ParamList/01.json');
        $this->transpiler->transpileParamList($data);

        self::assertEquals([
            'x' => [
                'name' => 'x',
                'type' => [
                    'value' => 'Integer',
                ],
            ],
            'y' => [
                'name' => 'y',
                'type' => [
                    'value' => 'Integer',
                ],
            ],
        ], $this->transpiler->inParameters);
    }

    /** @dataProvider typeDeclFromDeclContextProvider */
    public function testTranspileTypeDecl_from_decl_context(
        string $fixturePath, string $parentNodeType, array $expected): void
    {
        $data = $this->loadFixture($fixturePath);
        $actual = $this->transpiler->transpileTypeDecl($data, $parentNodeType);

        self::assertEquals($expected, $actual);
    }

    public function typeDeclFromDeclContextProvider(): array
    {
        return [
            [
                'fixturePath' => 'nodetypes/TypeDecl/FromDeclContext/01.json',
                'parentNodeType' => 'Decl',
                'expected' => ['value' => ['value' => 'Integer']],
            ],
            [
                'fixturePath' => 'nodetypes/TypeDecl/FromDeclContext/02.json',
                'parentNodeType' => 'Decl',
                'expected' => ['value' => ['value' => 'C.unsigned']],
            ],
        ];
    }

    public function testTranspileTypeDecl_from_func_decl_context(): void
    {
        $data = $this->loadFixture('nodetypes/TypeDecl/FromFuncDeclContext/01.json');
        $this->transpiler->transpileTypeDecl($data, 'FuncDecl');

        self::assertEquals('Integer', $this->transpiler->outParameter['type']);
    }

    /** @dataProvider funcDeclProvider */
    public function testTranspileFuncDecl(string $fixturePath, array $expectedInParameters): void
    {
        $data = $this->loadFixture($fixturePath);

        $this->transpiler = new FuncDefTranspiler();
        $this->transpiler->transpileFuncDecl($data);

        self::assertEquals($expectedInParameters, $this->transpiler->inParameters);
    }

    public function funcDeclProvider(): array
    {
        return [
            [
                'fixturePath' => 'nodetypes/FuncDecl/01.json',
                'expectedInParameters' => [
                    'x' => [
                        'name' => 'x',
                        'type' => [
                            'value' => 'Integer',
                        ],
                    ],
                    'y' => [
                        'name' => 'y',
                        'type' => [
                            'value' => 'Integer',
                        ],
                    ],
                ],
            ],
            [
                'fixturePath' => 'nodetypes/FuncDecl/02.json',
                'expectedInParameters' => [],
            ],
        ];
    }

    /** @dataProvider funcDefProvider */
    public function testTranspileFuncDef(string $fixturePath, array $expected): void
    {
        $data = $this->loadFixture($fixturePath);
        $actual = $this->transpiler->transpileFuncDef($data);

        self::assertEquals($expected, $actual);
    }

    public function funcDefProvider(): array
    {
        return [
            [
                'nodetypes/FuncDef/01.json',
                ['value' => "function foo (x : in Integer; y : in Integer)\nreturn Integer\nis\nbegin\n    result := ((x / 42) * y);\n    return result;\nend foo;"],
            ],
            [
                'nodetypes/FuncDef/02.json',
                ['value' => "procedure foo (number : in Integer)\nis\nbegin\n    printf(\"%d\", number);\nend foo;"],
            ],
            /*[
                'nodetypes/FuncDef/03.json',
                ['value' => "procedure Foo\nis\nbegin\n    for i in Integer range 0 .. 50 loop\nprintf(\"%d\", i);\nend loop;\nend Foo;"],
            ],*/
        ];
    }

    public function testTranspileAssigment(): void
    {
        $data = $this->loadFixture('nodetypes/Assignment/01.json');

        $expected = ['value' => 'i = 0', 'type' => 'Integer'];
        $actual = $this->transpiler->transpileAssignment($data);

        self::assertEquals($expected, $actual);
    }

    public function testTranspileExprList(): void
    {
        $data = $this->loadFixture('nodetypes/ExprList/01.json');

        $expected = ['value' => '"%d", i'];
        $actual = $this->transpiler->transpileExprList($data);

        self::assertEquals($expected, $actual);
    }

    public function testTranspileFuncCall(): void
    {
        $data = $this->loadFixture('nodetypes/FuncCall/01.json');

        $expected = ['value' => 'printf("%d", i)'];
        $actual = $this->transpiler->transpileFuncCall($data);

        self::assertEquals($expected, $actual);
    }

    /** @dataProvider identifierTypeProvider */
    public function testTranspileIdentifierType_01(string $fixturePath, array $expected): void
    {
        $data = $this->loadFixture($fixturePath);
        $actual = $this->transpiler->transpileIdentifierType($data);

        self::assertEquals($expected, $actual);
    }

    public function identifierTypeProvider(): array
    {
        return [
            [
                'nodetypes/IdentifierType/01.json',
                ['value' => 'Integer'],
            ],
            [
                'nodetypes/IdentifierType/02.json',
                ['value' => 'C.unsigned'],
            ],
        ];
    }

    public function testTranspileUnaryOp(): void
    {
        $data = $this->loadFixture('nodetypes/UnaryOp/++.json');

        $expected = ['value' => 'i := i + 1'];
        $actual = $this->transpiler->transpileUnaryOp($data);

        self::assertEquals($expected, $actual);
    }

    public function testTranspileFor(): void
    {
        $data = $this->loadFixture('nodetypes/For/01.json');

        $expected = ['value' => "for i in Integer range 0 .. 50 loop\nprintf(\"%d\", i);\nend loop"];
        $actual = $this->transpiler->transpileFor($data);

        self::assertEquals($expected, $actual);
    }

    /** @dataProvider fileASTProvider */
    public function testTranspileFileAST(string $fixturePath, array $expected): void
    {
        $data = $this->loadFixture($fixturePath);

        $actual = $this->transpiler->transpileFileAST($data);

        self::assertEquals($expected, $actual);
    }

    public function fileASTProvider(): array
    {
        return [
            [
                'nodetypes/FileAST/01.json',
                ['value' =>
                    "function foo (x : in Integer; y : in Integer)\nreturn Integer\nis\nbegin\n    result := ((x / 42) * y);\n    return result;\nend foo;\n\n"
                    . "end Transpiled;",
                ],
            ],
            [
                'nodetypes/FileAST/02.json',
                ['value' =>
                    "function first (x : in Integer; y : in Integer)\nreturn Integer\nis\nbegin\n    result := ((x / 42) * y);\n    return result;\nend first;\n\n"
                    . "procedure second (number : in Integer)\nis\nbegin\n    printf(\"%d\", number);\nend second;\n\n"
                    . "end Transpiled;",
                ],
            ],
        ];
    }

    public function testTranspileWhile(): void
    {
        $data = $this->loadFixture('nodetypes/While/01.json');

        $expected = ['value' => "while (i < 50) loop\ni := i + 1;\nend loop"];
        $actual = $this->transpiler->transpileWhile($data);

        self::assertEquals($expected, $actual);
    }

    public function testTranspileDoWhile(): void
    {
        $data = $this->loadFixture('nodetypes/DoWhile/01.json');

        $expected = ['value' => "loop\ni := i + 1;\nexit when not (i < 50);\nend loop"];
        $actual = $this->transpiler->transpileDoWhile($data);

        self::assertEquals($expected, $actual);
    }

    /** @dataProvider ifProvider */
    public function testTranspileIf(string $fixturePath, array $expected): void
    {
        $data = $this->loadFixture($fixturePath);

        $actual = $this->transpiler->transpileIf($data);

        self::assertEquals($expected, $actual);
    }

    public function ifProvider(): array
    {
        return [
            /*[
                'nodetypes/If/01.json',
                ['value' => "if (number <= 50) then\n"],
            ],*/
        ];
    }

    private function loadFixture(string $path): array
    {
        return \Safe\json_decode(\Safe\file_get_contents(self::TESTDATA_DIR . '/' . $path),
            true);
    }
}
