<?php

namespace LeoVie\C2Spark;

class AssignmentTranspiler extends Transpiler
{
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
}