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
        'unsigned int' => 'C.unsigned',
        'string' => 'String',
    ];

    protected const OP_MAPPING = [
        'p++' => '%s := %s + 1',
    ];

    public array $inParameters = [];
    public array $outParameter = ['name' => '', 'type' => ''];
    public array $variables = [];
    public string $functionName = '';
    public array $compounds = [];

    public function transpile(?array $data, string $parentNodeType): ?array
    {
        if ($data === null || $data === []) {
            return [];
        }

        $nodeType = $data['_nodetype'];
        switch ($nodeType) {
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
                $this->transpileReturn($data);
                break;
            case 'TypeDecl':
                return $this->transpileTypeDecl($data, $parentNodeType);
                break;
            case 'FileAST':
                return $this->transpileFileAST($data);
                break;
            case 'While':
                return $this->transpileWhile($data);
                break;
            case 'UnaryOp':
                return $this->transpileUnaryOp($data);
                break;
            case 'DoWhile':
                return $this->transpileDoWhile($data);
                break;
            case 'If':
                return $this->transpileIf($data);
                break;
        }

        return null;
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

        return [
            'value' => '(' . $left['value'] . ' ' . $op . ' ' . $right['value'] . ')',
        ];
    }

    public function transpileCompound(array $data): void
    {
        $code = '';
        foreach ($data['block_items'] as $blockItem) {
            $transpiled = $this->transpile($blockItem, $data['_nodetype']);
            if (!empty($transpiled)) {
                $code .= $transpiled['value'];
            }
        }

        $this->compounds[] = $code;
    }

    public function transpileReturn(array $data): void
    {
        $expr = $this->transpile($data['expr'], $data['_nodetype']);
        $this->outParameter['name'] = $expr['value'];
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
    public function transpileTypeDecl(array $data, string $parentNodeType): ?array
    {
        if ($parentNodeType == 'Decl') {
            return [
                'value' => $this->transpile($data['type'], $data['_nodetype']),
            ];
        } else if ($parentNodeType == 'FuncDecl') {
            $varType = $this->transpile($data['type'], $data['_nodetype']);
            $this->outParameter['type'] = $varType['value'];
            return null;
        }

        throw new Exception('No transpile method defined for parent node type "' . $parentNodeType . '".');
    }

    public function transpileFuncDecl(array $data): void
    {
        $this->transpile($data['args'], $data['_nodetype']);
        $this->transpile($data['type'], $data['_nodetype']);
    }

    public function transpileFuncDef(array $data): array
    {
        return (new FuncDefTranspiler())->transpile($data, $data['_nodetype']);
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
        if (!array_key_exists($cOp, self::OP_MAPPING)) {
            throw new Exception('No transpiled op found for C op "' . $cOp . '".');
        }

        $expr = $this->transpile($data['expr'], $data['_nodetype'])['value'];

        $opWithPlaceholders = self::OP_MAPPING[$cOp];
        $op = sprintf($opWithPlaceholders, $expr, $expr);

        return [
            'value' => $op,
        ];
    }

    /**
     * @throws Exception
     */
    public function transpileDecl(array $data, string $parentNodeType): ?array
    {
        if ($parentNodeType === 'Compound') {
            $name = $data['name'];
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
            return null;
        }
        if ($parentNodeType === 'FuncDef') {
            $this->functionName = ucfirst($data['name']);
            $this->transpile($data['type'], $data['_nodetype']);
            return null;
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
}