<?php

namespace LeoVie\C2Spark;

class IfTranspiler extends Transpiler
{
    public function transpileIf(array $data): array
    {
        $cond = $this->transpile($data['cond'], $data['_nodetype'])['value'];
        $code = "if $cond then\n";
        $ifTrue = $this->transpile($data['iftrue'], $data['_nodetype']);
        $ifFalse = $this->transpile($data['iffalse'], $data['_nodetype']);

        return [
            'value' => $code,
        ];
    }
}