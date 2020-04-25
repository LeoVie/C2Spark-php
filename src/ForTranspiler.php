<?php

namespace LeoVie\C2Spark;

use Exception;

class ForTranspiler extends Transpiler
{
    private string $condVariable;

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
        $this->condVariable = substr($condVariable, 1, strlen($condVariable) - 1);
        $condOperator = explode(' ', $cond)[1];
        $condValue = explode(' ', $cond)[2];
        $condValue = substr($condValue, 0, strlen($condValue) - 1);
        if ($this->condVariable === $variable && $condOperator === '<') {
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

        $code .= 'end loop';

        return [
            'value' => $code,
        ];
    }
}