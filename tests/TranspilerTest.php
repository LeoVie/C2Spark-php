<?php

namespace LeoVie\C2Spark\Tests;

use LeoVie\C2Spark\Transpiler;
use PHPUnit\Framework\TestCase;

class TranspilerTest extends TestCase
{
    private const TESTDATA_DIR = __DIR__ . '/testdata';

    private Transpiler $transpiler;

    protected function setUp(): void
    {
        $this->transpiler = new Transpiler();
    }

    public function testTranspileId(): void
    {
        $data = $this->loadJsonData('nodetypes/ID/01.json');
        $expected = ['value' => 'y'];
        $actual = $this->transpiler->transpileId($data);

        self::assertEquals($expected, $actual);
    }

    public function testTranspileConstant(): void
    {
        $data = $this->loadJsonData('nodetypes/Constant/01.json');
        $expected = ['value' => '42', 'type' => 'Integer'];
        $actual = $this->transpiler->transpileConstant($data);

        self::assertEquals($expected, $actual);
    }

    /** @dataProvider binaryOpProvider */
    public function testTranspileBinaryOp(string $fixturePath, array $expected): void
    {
        $data = $this->loadJsonData($fixturePath);
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
        $data = $this->loadJsonData($fixturePath);
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
        $data = $this->loadJsonData('nodetypes/Return/01.json');
        $this->transpiler->transpileReturn($data);

        self::assertEquals('result', $this->transpiler->outParameter['name']);
    }

    public function testTranspileParamList(): void
    {
        $data = $this->loadJsonData('nodetypes/ParamList/01.json');
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
        $data = $this->loadJsonData($fixturePath);
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
        $data = $this->loadJsonData('nodetypes/TypeDecl/FromFuncDeclContext/01.json');
        $this->transpiler->transpileTypeDecl($data, 'FuncDecl');

        self::assertEquals('Integer', $this->transpiler->outParameter['type']);
    }

    /** @dataProvider funcDeclProvider */
    public function testTranspileFuncDecl(
        string $fixturePath, array $expectedOutParameter, array $expectedInParameters): void
    {
        $data = $this->loadJsonData($fixturePath);
        $this->transpiler->transpileFuncDecl($data);

        self::assertEquals($expectedOutParameter, $this->transpiler->outParameter);
        self::assertEquals($expectedInParameters, $this->transpiler->inParameters);
    }

    public function funcDeclProvider(): array
    {
        return [
            [
                'fixturePath' => 'nodetypes/FuncDecl/01.json',
                'expectedOutParameter' => ['type' => 'Integer', 'name' => ''],
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
                'expectedOutParameter' => ['type' => 'void', 'name' => ''],
                'expectedInParameters' => [],
            ],
        ];
    }

    /** @dataProvider funcDefProvider */
    public function testTranspileFuncDef(string $fixturePath, array $expected): void
    {
        $data = $this->loadJsonData($fixturePath);
        $actual = $this->transpiler->transpileFuncDef($data);

        self::assertEquals($expected, $actual);
    }

    public function funcDefProvider(): array
    {
        return [
            [
                'nodetypes/FuncDef/01.json',
                ['value' => "procedure Foo (x : in Integer; y : in Integer; result : out Integer) is\nbegin\n    result := ((x / 42) * y);\nend Foo;"],
            ],
            [
                'nodetypes/FuncDef/02.json',
                ['value' => "procedure Foo (number : in Integer) is\nbegin\n    printf(\"%d\", number);\nend Foo;"],
            ],
            [
                'nodetypes/FuncDef/03.json',
                ['value' => "procedure Foo (number : in Integer) is\nbegin\n    printf(\"%d\", number);\nend Foo;"],
            ],
        ];
    }

    public function testTranspileAssigment(): void
    {
        $data = $this->loadJsonData('nodetypes/Assignment/01.json');

        $expected = ['value' => 'i = 0', 'type' => 'Integer'];
        $actual = $this->transpiler->transpileAssignment($data);

        self::assertEquals($expected, $actual);
    }

    public function testTranspileExprList(): void
    {
        $data = $this->loadJsonData('nodetypes/ExprList/01.json');

        $expected = ['value' => '"%d", i'];
        $actual = $this->transpiler->transpileExprList($data);

        self::assertEquals($expected, $actual);
    }

    public function testTranspileFuncCall(): void
    {
        $data = $this->loadJsonData('nodetypes/FuncCall/01.json');

        $expected = ['value' => 'printf("%d", i)'];
        $actual = $this->transpiler->transpileFuncCall($data);

        self::assertEquals($expected, $actual);
    }

    /** @dataProvider identifierTypeProvider */
    public function testTranspileIdentifierType_01(string $fixturePath, array $expected): void
    {
        $data = $this->loadJsonData($fixturePath);
        $actual = $this->transpiler->transpileIdentifierType($data);

        self::assertEquals($expected, $actual);
    }

    public function identifierTypeProvider(): array
    {
        return [
            [
                'nodetypes/IdentifierType/01.json',
                ['value' => 'Integer']
            ],
            [
                'nodetypes/IdentifierType/02.json',
                ['value' => 'C.unsigned']
            ],
        ];
    }

    public function testTranspileUnaryOp(): void
    {
        $data = $this->loadJsonData('nodetypes/UnaryOp/++.json');

        $expected = ['value' => 'i := i + 1'];
        $actual = $this->transpiler->transpileUnaryOp($data);

        self::assertEquals($expected, $actual);
    }

    public function testTranspileFor(): void
    {
        $data = $this->loadJsonData('nodetypes/For/01.json');

        $expected = ['value' => "for i in Integer range 0 .. 50 loop\nprintf(\"%d\", i);\nend loop;"];
        $actual = $this->transpiler->transpileFor($data);

        self::assertEquals($expected, $actual);
    }

    private function loadJsonData(string $path): array
    {
        return \Safe\json_decode(\Safe\file_get_contents(self::TESTDATA_DIR . '/' . $path),
            true);
    }
}
