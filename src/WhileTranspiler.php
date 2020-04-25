<?php

namespace LeoVie\C2Spark;

class WhileTranspiler extends Transpiler
{
    public function transpileWhile(array $data): array
    {
        $code = 'while ';
        $cond = $this->transpile($data['cond'], $data['_nodetype']);
        $code .= $cond['value'] . ' loop' . "\n";

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