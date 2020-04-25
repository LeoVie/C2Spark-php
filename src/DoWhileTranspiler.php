<?php

namespace LeoVie\C2Spark;

class DoWhileTranspiler extends Transpiler
{
    public function transpileDoWhile(array $data): array
    {
        $code = "loop\n";
        $cond = $this->transpile($data['cond'], $data['_nodetype']);

        $this->transpile($data['stmt'], $data['_nodetype']);

        foreach ($this->compounds as $key => $compound) {
            $code .= $compound . ";\n";
        }

        $code .= "exit when not ${cond['value']};\n";
        $code .= 'end loop';

        return [
            'value' => $code,
        ];
    }
}