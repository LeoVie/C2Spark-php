<?php

namespace LeoVie\C2Spark;

use Exception;

class Transpiler
{
    protected const TYPE_MAPPING = [
        'int' => 'Integer',
        'short' => 'Short_Integer',
        'signed char' => 'Short_Short_Integer',
        'long' => 'Long_Integer',
        'long long' => 'Long_Long_Integer',
        # 'float' => 'Short_Float',
        'float' => 'Float',
        'double' => 'Long_Float',
        '_' => 'Long_Long_Float',
        'unsigned char' => 'Unbounded_String',
        'void' => 'void',
        'unsigned int' => 'Interfaces.C.unsigned',
        'string' => 'String',
    ];

    protected const UNARY_OP_MAPPING = [
        'p++' => '%s := %s + 1',
        '-' => '-%s',
    ];

    protected const BINARY_OP_MAPPING = [
        '==' => '=',
        '||' => 'or',
        '&&' => 'and',
    ];

    public array $inParameters = [];
    public array $outParameter = ['name' => '', 'type' => ''];
    public array $variables = [];
    public string $functionName = '';
    public array $compounds = [];
    public array $functionHeads = [];
    private array $fileCode;

    public function transpile(?array $data, string $parentNodeType): array
    {
        if ($data === null || $data === []) {
            return [];
        }

        $nodeType = $data['_nodetype'];
        switch ($nodeType) {
            case 'FileAST':
                $this->fileCode = $this->transpileFileAST($data);
                return $this->fileCode;
            case 'Assignment':
                return $this->transpileAssignment($data);
            case 'BinaryOp':
                return $this->transpileBinaryOp($data);
            case 'Compound':
                $this->transpileCompound($data);
                break;
            case 'Constant':
                return $this->transpileConstant($data);
            case 'Decl':
                return $this->transpileDecl($data, $parentNodeType);
            case 'ExprList':
                return $this->transpileExprList($data);
            case 'For':
                return $this->transpileFor($data);
            case 'FuncCall':
                return $this->transpileFuncCall($data);
            case 'FuncDecl':
                $this->transpileFuncDecl($data);
                break;
            case 'FuncDef':
                return $this->transpileFuncDef($data);
            case 'ID':
                return $this->transpileId($data);
            case 'IdentifierType':
                return $this->transpileIdentifierType($data);
            case 'ParamList':
                $this->transpileParamList($data);
                break;
            case 'Return':
                return $this->transpileReturn($data);
            case 'TypeDecl':
                return $this->transpileTypeDecl($data, $parentNodeType);
            case 'While':
                return $this->transpileWhile($data);
            case 'UnaryOp':
                return $this->transpileUnaryOp($data);
            case 'DoWhile':
                return $this->transpileDoWhile($data);
            case 'If':
                return $this->transpileIf($data);
        }

        return [];
    }

    public function transpileId(array $data): array
    {
        return [
            'value' => $data['name'],
        ];
    }

    /**
     * @throws Exception
     */
    public function transpileConstant(array $data): array
    {
        $value = $data['value'];
        $cType = $data['type'];
        if (!array_key_exists($cType, self::TYPE_MAPPING)) {
            throw new Exception('No transpiled type found for C type "' . $cType . '".');
        }

        return [
            'value' => $value,
            'type' => self::TYPE_MAPPING[$cType],
        ];
    }

    public function transpileBinaryOp(array $data): array
    {
        $left = $this->transpile($data['left'], $data['_nodetype']);
        $right = $this->transpile($data['right'], $data['_nodetype']);

        $op = $data['op'];
        if (array_key_exists($op, self::BINARY_OP_MAPPING)) {
            $op = self::BINARY_OP_MAPPING[$op];
        }

        return [
            'value' => sprintf("(%s %s %s)", $left['value'], $op, $right['value']),
        ];
    }

    public function transpileCompound(array $data): void
    {
        foreach ($data['block_items'] as $blockItem) {
            $transpiled = $this->transpile($blockItem, $data['_nodetype']);
            if (!empty($transpiled)) {
                $this->compounds[] = $transpiled['value'];
            }
        }
    }

    public function transpileReturn(array $data): array
    {
        $expr = $this->transpile($data['expr'], $data['_nodetype']);
        return [
            'value' => 'return ' . $expr['value'],
        ];
    }

    public function transpileParamList(array $data): void
    {
        foreach ($data['params'] as $param) {
            $this->transpile($param, $data['_nodetype']);
        }
    }

    /**
     * @throws Exception
     */
    public function transpileTypeDecl(array $data, string $parentNodeType): array
    {
        if ($parentNodeType === 'Decl') {
            $value = $this->transpile($data['type'], $data['_nodetype']);
            return [
                'value' => $value,
            ];
        } else if ($parentNodeType == 'FuncDecl') {
            $varType = $this->transpile($data['type'], $data['_nodetype']);
            $this->outParameter['type'] = $varType['value'];
            return [
                'value' => $this->transpile($data['type'], $data['_nodetype']),
            ];
        }

        throw new Exception('No transpile method defined for parent node type "' . $parentNodeType . '".');
    }

    public function transpileFuncDecl(array $data): void
    {
        (new FuncDefTranspiler($this->variables))->transpile($data, $data['_nodetype']);
    }

    public function transpileFuncDef(array $data): array
    {
        $funcDefTranspiler = new FuncDefTranspiler([]);
        $transpiled = $funcDefTranspiler->transpile($data, $data['_nodetype']);
        $this->functionHeads[] = $funcDefTranspiler->getFunctionHead();
        $this->variables = $funcDefTranspiler->getVariables();

        return $transpiled;
    }

    public function transpileAssignment(array $data): array
    {
        return (new AssignmentTranspiler())->transpile($data, $data['_nodetype']);
    }

    public function transpileFor(array $data): array
    {
        return (new ForTranspiler())->transpile($data, $data['_nodetype']);
    }

    public function transpileWhile(array $data): array
    {
        return (new WhileTranspiler())->transpile($data, $data['_nodetype']);
    }

    public function transpileDoWhile(array $data): array
    {
        return (new DoWhileTranspiler())->transpile($data, $data['_nodetype']);
    }

    public function transpileIf(array $data): array
    {
        return (new IfTranspiler())->transpile($data, $data['_nodetype']);
    }

    public function transpileExprList(array $data): array
    {
        $transpiledExpressions = [];
        foreach ($data['exprs'] as $expr) {
            $transpiledExpressions[] = $this->transpile($expr, $data['_nodetype'])['value'];
        }

        return [
            'value' => implode(', ', $transpiledExpressions),
        ];
    }

    public function transpileFuncCall(array $data): array
    {
        $name = $this->transpile($data['name'], $data['_nodetype']);
        $args = $this->transpile($data['args'], $data['_nodetype']);

        return [
            'value' => $name['value'] . '(' . $args['value'] . ')',
        ];
    }

    /**
     * @throws Exception
     */
    public function transpileIdentifierType(array $data): array
    {
        $cType = implode(' ', $data['names']);
        if (!array_key_exists($cType, self::TYPE_MAPPING)) {
            throw new Exception('No transpiled type found for C type "' . $cType . '".');
        }

        return [
            'value' => self::TYPE_MAPPING[$cType],
        ];
    }

    /**
     * @throws Exception
     */
    public function transpileUnaryOp(array $data): array
    {
        $cOp = $data['op'];
        if (!array_key_exists($cOp, self::UNARY_OP_MAPPING)) {
            throw new Exception('No transpiled op found for C op "' . $cOp . '".');
        }

        $expr = $this->transpile($data['expr'], $data['_nodetype'])['value'];

        $opWithPlaceholders = self::UNARY_OP_MAPPING[$cOp];
        $op = sprintf($opWithPlaceholders, $expr, $expr);

        return [
            'value' => $op,
        ];
    }

    /**
     * @throws Exception
     */
    public function transpileDecl(array $data, string $parentNodeType): array
    {
        if ($parentNodeType === 'Compound') {
            $name = $data['name'];
            $type = $this->transpile($data['type'], $data['_nodetype']);
            $this->variables[] = [
                'name' => $name,
                'type' => $type
            ];
            $init = $this->transpile($data['init'], $data['_nodetype']);
            if (empty($init)) {
                return ['value' => $name];
            }
            return [
                'value' => $name . ' := ' . $init['value'],
            ];
        }
        if ($parentNodeType === 'ParamList') {
            $name = $data['name'];
            $paramType = $this->transpile($data['type'], $data['_nodetype']);

            $this->inParameters[$name] = ['name' => $name, 'type' => $paramType['value']];
            return [];
        }
        if ($parentNodeType === 'FuncDef') {
            $this->functionName = $data['name'];
            $this->transpile($data['type'], $data['_nodetype']);
            return [];
        }

        throw new Exception('No transpilation method defined for parent node type "' . $parentNodeType . '".');
    }

    public function transpileFileAST(array $data): array
    {
        $code = '';

        foreach ($data['ext'] as $ext) {
            $code .= $this->transpile($ext, $data['_nodetype'])['value'];
            $code .= "\n\n";
        }

        return [
            'value' => $code,
        ];
    }

    public function getAdbContent(): string
    {
        $code = "package body Transpiled with SPARK_Mode => On is\n";
        $code .= $this->fileCode['value'] . "\n";
        $code .= 'end Transpiled;';

        return $code;
    }

    public function getAdsContent(): string
    {
        $code = 'with Interfaces.C; ';
        $code .= 'use type Interfaces.C.Unsigned; ';
        $code .= "\n\n";
        $code .= "package Transpiled with SPARK_Mode => On is\n";
        foreach ($this->functionHeads as $functionHead) {
            $code .= "$functionHead;\n";
        }
        $code .= 'end Transpiled;';

        return $code;
    }
}