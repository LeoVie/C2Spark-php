<?php

namespace LeoVie\C2Spark;

use Exception;

class Transpiler
{
    private const TYPE_MAPPING = [
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

    private const OP_MAPPING = [
        'p++' => '%s := %s + 1',
    ];

    public array $inParameters = [];
    public array $outParameter = ['name' => '', 'type' => ''];
    public string $functionName = '';
    public array $compounds = [];
    public array $compoundsStacks = [];

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
        $this->transpile($data['body'], $data['_nodetype']);
        $this->transpile($data['decl'], $data['_nodetype']);

        $code = '';
        $code .= 'procedure ' . $this->functionName . ' (';
        foreach ($this->inParameters as $key => $inParameter) {
            $code .= $inParameter['name'] . ' : in ' . $inParameter['type']['value'] . '; ';
        }
        if ($this->outParameter['type'] !== 'void') {
            $code .= $this->outParameter['name'] . ' : out ' . $this->outParameter['type'];
        }
        if (\Safe\substr($code, strlen($code) - 2, 2) === '; ') {
            $code = \Safe\substr($code, 0, strlen($code) - 2);
        }
        $code .= ") is\n";
        $code .= "begin\n";
        foreach ($this->compounds as $key => $compound) {
            $code .= '    ' . $compound . ";\n";
        }
        $code .= 'end ' . $this->functionName . ';';

        return ['value' => $code];
    }

    public function transpileAssignment(array $data): array
    {
        $left = $this->transpile($data['lvalue'], $data['_nodetype']);
        $op = $data['op'];
        $right = $this->transpile($data['rvalue'], $data['_nodetype']);

        return [
            'value' => $left['value'] . ' ' . $op . ' ' . $right['value'],
            'type' => $right['type'],
        ];
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
    public function transpileFor(array $data): array
    {
        $init = $this->transpile($data['init'], $data['_nodetype']);

        $variable = explode(' = ', $init['value'])[0];
        $initialValue = explode(' = ', $init['value'])[1];
        $variableType = $init['type'];

        $code = '';
        $code .= 'for ' . $variable . ' in ' . $variableType;

        $cond = $this->transpile($data['cond'], $data['_nodetype'])['value'];
        $condVariable = explode(' ', $cond)[0];
        $condVariable = substr($condVariable, 1, strlen($condVariable) - 1);
        $condOperator = explode(' ', $cond)[1];
        $condValue = explode(' ', $cond)[2];
        $condValue = substr($condValue, 0, strlen($condValue) - 1);
        if ($condVariable === $variable && $condOperator === '<') {
            $code .= ' range ' . $initialValue . ' .. ' . $condValue;
        } else {
            throw new Exception('No range definition exists for operator "' . $condOperator . '".');
        }

        $code .= " loop\n";

        // TODO: data['next'] handling

        $this->transpile($data['stmt'], $data['_nodetype']);

        foreach ($this->compounds as $key => $compound) {
            $code .= $compound . ";\n";
        }

        $code .= 'end loop;';

        return [
            'value' => $code,
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
}