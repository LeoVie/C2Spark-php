<?php

namespace LeoVie\C2Spark;

class FuncDefTranspiler extends Transpiler
{
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
}