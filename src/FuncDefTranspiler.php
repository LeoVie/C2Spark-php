<?php

namespace LeoVie\C2Spark;

use Safe\Exceptions\StringsException;

class FuncDefTranspiler extends Transpiler
{
    /** @var array<string, string> **/
    public array $returnType;

    public function transpileFuncDecl(array $data): void
    {
        $this->transpile($data['args'], $data['_nodetype']);
        $this->returnType = $this->transpile($data['type'], $data['_nodetype'])['value'];
    }

    public function transpileFuncDef(array $data): array
    {
        $this->transpile($data['body'], $data['_nodetype']);
        $this->transpile($data['decl'], $data['_nodetype']);

        $code = $this->determineSubprogramKeyword();
        $code .= " $this->functionName (";
        $code = $this->concatInParameters($code);
        $code .= ")\n";
        $code = $this->appendReturnIfExists($code);
        $code .= "is\n";
        $code .= "begin\n";
        $code = $this->appendCompounds($code);
        $code .= 'end ' . $this->functionName . ';';

        return ['value' => $code];
    }

    private function determineSubprogramKeyword(): string
    {
        if ($this->isFunction()) {
            return 'function';
        }

        return 'procedure';
    }

    private function isFunction(): bool
    {
        return $this->returnType['value'] !== 'void';
    }

    private function concatInParameters(string $code): string
    {
        foreach ($this->inParameters as $key => $inParameter) {
            $code .= $inParameter['name'] . ' : in ' . $inParameter['type']['value'] . '; ';
        }
        $code = $this->removeTrailingSemicolon($code);

        return $code;
    }

    /**
     * @throws StringsException
     */
    private function removeTrailingSemicolon(string $code): string
    {
        if (\Safe\substr($code, strlen($code) - 2, 2) === '; ') {
            $code = \Safe\substr($code, 0, strlen($code) - 2);
        }

        return $code;
    }

    private function appendReturnIfExists(string $code): string
    {
        if ($this->isFunction()) {
            $code .= 'return ' . $this->returnType['value'] . "\n";
        }

        return $code;
    }

    private function appendCompounds(string $code): string
    {
        foreach ($this->compounds as $key => $compound) {
            $code .= '    ' . $compound . ";\n";
        }

        return $code;
    }
}