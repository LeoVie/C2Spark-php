<?php

namespace LeoVie\C2Spark;

class IfTranspiler extends Transpiler
{
    public function transpileIf(array $data): array
    {
        $cond = $this->transpile($data['cond'], $data['_nodetype'])['value'];
        $code = "if $cond then ";

        $this->transpile($data['iftrue'], $data['_nodetype']);
        foreach ($this->compounds as $compound) {
            $code .= $compound . "; ";
        }

        $this->compounds = [];
        $this->transpile($data['iffalse'], $data['_nodetype']);
        if (!empty($this->compounds)) {
            $code .= 'else ';
            foreach ($this->compounds as $compound) {
                $code .= $compound . "; ";
            }

        }
        $code .= "end if\n";

        return [
            'value' => $code,
        ];
    }
}