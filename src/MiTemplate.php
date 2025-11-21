<?php

/**
 * MiTemplate - Biblioteca de Template para separar HTML do PHP.
 *
 * Copyright (C) 2025 Murilo Gomes Julio
 * SPDX-License-Identifier: LGPL-2.1-only
 *
 * @author    Murilo Gomes Julio
 * @email     mugomesoficial@gmail.com
 * @homepage  www.mugomes.com.br
 * @version   0.1.0
 */

namespace MiTemplate;

use Minifier\TinyHtmlMinifier;

class MiTemplate
{
    /**
     * Uma lista de variáveis ​​de documentos existentes.
     */
    protected array $vars = [];

    /**
     * Um hash com vars e valores definidos pelo usuário.
     */
    protected array $values = [];

    /**
     * Um hash de variáveis ​​de propriedades de objeto existentes no documento.
     */
    private array $objectProperties = [];

    /**
     * Um hash das instâncias de objeto definidas pelo usuário.
     */
    protected array $objectInstances = [];

    /**
     * Lista de modificadores usados
     */
    protected array $modifiers = [];

    /**
     * Uma lista de todos os blocos reconhecidos automáticos.
     */
    private array $blocks = [];

    /**
     * Uma lista de todos os blocos que contém pelo menos um bloco "criança".
     */
    private array $parentBlocks = [];

    /**
     * Lista de blocos analisados
     */
    private array $parsedBlocks = [];

    /**
     * Lista de blocos para finalizar
     */
    private array $finallyBlocks = [];

    /**
     * Descreve o método de substituição para blocos.
     */
    private bool $accurateParsing;

    // Ativar Minify
    private bool $enabledMinify = false;
    private array $optMinify = [
        'collapse_whitespace' => true,
        'disable_comments' => false
    ];

    /**
     * Expressão regular para encontrar nomes VAR e Block.
     */
    private const REGEX_VALID_NAME = "([[:alnum:]_]+(?: [[:alnum:]_]+)*)";

    // ==================== Métodos de inicialização ====================

    /**
     * Cria um novo modelo, usando o nome do $ FileName como arquivo principal.
     *
     * @param string $filename Caminho do arquivo do arquivo a ser carregado
     * @param boolean $accurate True Para análise de bloco precisa
     */
    public function __construct(string $filename, bool $accurate = false)
    {
        $this->accurateParsing = $accurate;
        $this->loadFile('.', $filename);
    }

    /**
     * Coloque o conteúdo de $ FILENAME na variável de modelo identificada por $ Varname
     *
     * @param string $varname Modelo existente var
     * @param string $filename Arquivo a ser carregado
     */
    public function addFile(string $varname, string $filename)
    {
        try {
            if (!$this->variableExists($varname)) {
                throw new \InvalidArgumentException("addFile: var $varname does not exist");
            }

            $this->loadFile($varname, $filename);
        } catch (\InvalidArgumentException $ex) {
            echo $ex->getMessage();
        }
    }

    // ==================== Métodos de acesso à propriedade ====================

    /**
     * Método do conjunto de propriedades
     *
     * @param string $varname Modelo Var Nome
     * @param mixed $value Modelo Var valor
     */
    public function set(string $varname, mixed $value)
    {
        try {
            if (!$this->variableExists($varname)) {
                throw new \RuntimeException("var $varname does not exist");
            }

            if (is_object($value)) {
                $this->objectInstances[$varname] = $value;

                if (!isset($this->objectProperties[$varname])) {
                    $this->objectProperties[$varname] = [];
                }

                $stringValue = method_exists($value, "__toString")
                    ? $value->__toString()
                    : 'Object: ' . json_encode($value);
            } elseif (is_array($value)) {
                $stringValue = implode(', ', $value);
            } else {
                $stringValue = $value;
            }

            $this->setValue($varname, $stringValue);
            return $value;
        } catch (\RuntimeException $ex) {
            echo $ex->getMessage();
        }
    }

    /**
     * Método Getter Propriedades
     *
     * @param string $varname Nome da Variável do Modelo
     */
    public function get(string $varname): mixed
    {
        $valor = '';
        try {
            $varReference = "{{" . $varname . "}}";
            
            if (isset($this->values[$varReference])) {
                $valor = $this->values[$varReference];
            } elseif (isset($this->objectInstances[$varname])) {
                $valor = $this->objectInstances[$varname];
            }

            throw new \RuntimeException("var $varname does not exist");
        } catch (\RuntimeException $ex) {
            echo $ex->getMessage();
        } finally {
            return $valor;
        }
    }

    /**
     * Verifique se existe um modelo var
     *
     * @param string $varname Modelo Var Nome
     * @return boolean True Se o modelo Var existir
     */
    public function variableExists(string $varname): bool
    {
        return in_array($varname, $this->vars);
    }

    // ==================== Métodos de carregamento de arquivos ====================

    /**
     * Carrega um arquivo identificado por $ filename
     *
     * @param string $varname Contém o nome de uma variável para carregar
     * @param string $filename Nome do arquivo a ser carregado
     */
    protected function loadFile(string $varname, string $filename)
    {
        try {
            if (!file_exists($filename)) {
                throw new \InvalidArgumentException("file $filename does not exist");
            }

            if ($this->isPhpFile($filename)) {
                $content = $this->loadPhpFile($filename);
            } else {
                $content = $this->loadTemplateFile($filename);
            }

            $this->setValue($varname, $content);

            if (!$this->isPhpFile($filename)) {
                $blocks = $this->identifyBlocksAndVars($content, $varname);
                $this->createBlocks($blocks);
            }
        } catch (\InvalidArgumentException $ex) {
            echo $ex->getMessage();
        }
    }

    /**
     * Verifique se o arquivo é um arquivo php
     *
     * @param string $filename Arquivo para verificar
     * @return boolean True Se o arquivo php
     */
    protected function isPhpFile(string $filename): bool
    {
        $phpExtensions = ['.php', '.php5', '.cgi'];
        $fileExtension = substr($filename, strripos($filename, '.'));

        return in_array(strtolower($fileExtension), $phpExtensions);
    }

    /**
     * Carregar e executar um arquivo php
     *
     * @param string $filename Arquivo php para carregar
     * @return string Saída do arquivo php
     */
    protected function loadPhpFile(string $filename): string
    {
        ob_start();
        include_once($filename);
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    /**
     * Carregar e pré-processar um arquivo de modelo
     *
     * @param string $filename Arquivo de modelo para carregar
     * @return string Processed conteúdo de modelo
     */
    protected function loadTemplateFile(string $filename): string
    {
        $content = '';
        try {
            $content = file_get_contents($filename);
            $content = preg_replace("/<!---.*?--->/smi", "", $content);

            if (empty($content)) {
                throw new \InvalidArgumentException("file $filename is empty");
            }
        } catch (\InvalidArgumentException $ex) {
            echo $ex->getMessage();
        } finally {
            return $content;
        }
    }

    // ==================== Métodos de análise de modelos ====================

    /**
     * Identifique todos os blocos e variáveis ​​automaticamente
     *
     * @param string $content Conteúdo do arquivo
     * @param string $varname Contém o nome variável do arquivo
     * @return array Uma variedade de blocos e seus filhos
     */
    protected function identifyBlocksAndVars(string &$content, string $varname): array
    {
        $blocks = [];
        $queuedBlocks = [];

        $this->identifyVariables($content);

        // Lidar com HTML minificado adicionando novas linhas após comentários
        $lines = explode("\n", $content);
        if (count($lines) === 1) {
            $content = str_replace(']', "]\n", $content);
        }

        // Processe cada linha para identificar blocos
        foreach (explode("\n", $content) as $line) {
            if (strpos($line, "[") !== false) {
                $this->processLineForBlocks($line, $varname, $queuedBlocks, $blocks);
            }
        }

        return $blocks;
    }

    /**
     * Identifique todos os blocos definidos pelo usuário em uma linha
     *
     * @param string $line Uma linha do arquivo de conteúdo
     * @param string $varname Identificador variável do nome do arquivo
     * @param array $queuedBlocks Lista dos blocos atuais da fila
     * @param array $blocks Lista de todos os blocos identificados
     */
    protected function processLineForBlocks(string $line, string $varname, array &$queuedBlocks, array &$blocks)
    {
        $beginPattern = "/\[BEGIN\s+(" . self::REGEX_VALID_NAME . ")\]/i";
        $endPattern   = "/\[END\s+(" . self::REGEX_VALID_NAME . ")\]/i";

        // Verifique o bloco de início
        if (preg_match($beginPattern, $line, $matches)) {
            $blockName = $matches[1];
            $parentBlock = empty($queuedBlocks) ? $varname : end($queuedBlocks);

            if (!isset($blocks[$parentBlock])) {
                $blocks[$parentBlock] = [];
            }

            $blocks[$parentBlock][] = $blockName;
            $queuedBlocks[] = $blockName;
        }
        // Verifique o bloco final
        elseif (preg_match($endPattern, $line)) {
            array_pop($queuedBlocks);
        }
    }

    /**
     * Identifica todas as variáveis ​​definidas no documento
     *
     * @param string $content Conteúdo do arquivo
     */
    protected function identifyVariables(string &$content)
    {
        $pattern = "/{(" . self::REGEX_VALID_NAME . ")((\\-\\>(" . self::REGEX_VALID_NAME . "))*)?" .
            "((\\|.*?)*)?}/";

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->processVariableMatch($match);
            }
        }
    }

    /**
     * Processe uma correspondência variável encontrada no modelo
     *
     * @param array $match Resultado da correspondência regex
     */
    protected function processVariableMatch(array $match)
    {
        $varName = $match[1];

        // Propriedade de objeto detectado
        if (!empty($match[3])) {
            $propertyPath = $match[3];

            if (
                !isset($this->objectProperties[$varName]) ||
                !in_array($propertyPath, $this->objectProperties[$varName])
            ) {
                $this->objectProperties[$varName][] = $propertyPath;
            }
        }

        // Modificadores detectados
        if (!empty($match[7])) {
            $modifierKey = $varName . (!empty($match[3]) ? $match[3] : '');
            $modifierExpression = $varName . (!empty($match[3]) ? $match[3] : '') . $match[7];

            if (
                !isset($this->modifiers[$modifierKey]) ||
                !in_array($modifierExpression, $this->modifiers[$modifierKey])
            ) {
                $this->modifiers[$modifierKey][] = $modifierExpression;
            }
        }

        // Variáveis ​​comuns
        if (!in_array($varName, $this->vars)) {
            $this->vars[] = $varName;
        }
    }

    // ==================== Métodos de gerenciamento de blocos ====================

    /**
     * Crie todos os blocos identificados
     *
     * @param array $blocks Contém todos os nomes de blocos identificados
     */
    protected function createBlocks(array $blocks)
    {
        try {
            $this->parentBlocks = array_merge($this->parentBlocks, $blocks);

            foreach ($blocks as $parent => $childBlocks) {
                foreach ($childBlocks as $childBlock) {
                    if (in_array($childBlock, $this->blocks)) {
                        throw new \UnexpectedValueException("duplicated block: $childBlock");
                    }

                    $this->blocks[] = $childBlock;
                    $this->setupBlock($parent, $childBlock);
                }
            }
        } catch (\UnexpectedValueException $ex) {
            echo $ex->getMessage();
        }
    }

    /**
     * Configure um bloco extraindo -o de seus pais
     *
     * @param string $parent Nome da variável pai
     * @param string $block Nome do bloco a ser substituído
     */
    protected function setupBlock(string $parent, string $block)
    {
        try {
            $blockValueVar = $block . '_value';
            $parentContent = $this->getVar($parent);

            // Escolha o padrão com base na configuração de precisão
            if ($this->accurateParsing) {
                $parentContent = str_replace("\r\n", "\n", $parentContent);
                $pattern = "/\[BEGIN\s+$block\]\n*(\s*.*?\n?)\[END\s+$block\]\n*" .
                    "((\s*.*?\n?)\[FINALLY\s+$block\]\n?)?/sm";
            } else {
                $pattern = "/\[BEGIN\s+$block\]\s*(\s*.*?\s*)\[END\s+$block\]\s*" .
                    "((\s*.*?\s*)\[FINALLY\s+$block\])?\s*/sm";
            }


            // Extraia o conteúdo do bloco
            if (!preg_match($pattern, $parentContent, $matches)) {
                throw new \UnexpectedValueException("mal-formed block $block");
            }

            $this->setValue($blockValueVar, '');
            $this->setValue($block, $matches[1]);
            $this->setValue($parent, preg_replace($pattern, "{{" . $blockValueVar . "}}", $parentContent));

            // Armazenar finalmente bloqueia o conteúdo se presente
            if (isset($matches[3])) {
                $this->finallyBlocks[$block] = $matches[3];
            }
        } catch (\UnexpectedValueException $ex) {
            echo $ex->getMessage();
        }
    }

    /**
     * Atribuir manualmente um bloco filho a um bloco pai
     *
     * @param string $parent Bloco pai
     * @param string $block Bloco de crianças
     */
    public function setParent(string $parent, string $block)
    {
        $this->parentBlocks[$parent][] = $block;
    }

    // ==================== Métodos de gerenciamento de valor ====================

    /**
     * Método interno do setValue
     *
     * @param string $varname Nome variável
     * @param string $value Valor variável
     */
    protected function setValue(string $varname, string $value)
    {
        $this->values['{{' . $varname . '}}'] = $value;
    }

    /**
     * Retorna o valor da variável
     *
     * @param string $varname O nome da variável
     * @return string O valor da variável
     */
    protected function getVar(string $varname): string
    {
        return $this->values['{{' . $varname . '}}'];
    }

    /**
     * Limpe o valor de uma variável
     *
     * @param string $varname Nome variável a ser limpo
     */
    public function clear(string $varname)
    {
        $this->setValue($varname, "");
    }

    // ==================== Métodos de processamento do modificador ====================

    /**
     * Aplicar modificadores a um valor
     *
     * @param string $value Texto a ser modificado
     * @param string $modifierExpression Expressão do modificador
     * @return string Valor modificado
     */
    protected function applyModifiers(string $value, string $modifierExpression): string
    {
        $modifiers = explode('|', $modifierExpression);

        // O primeiro elemento é o nome da variável, pule-o
        for ($i = 1; $i < count($modifiers); $i++) {
            $modifierParts = explode(":", $modifiers[$i]);
            $functionName = $modifierParts[0];
            $parameters = array_slice($modifierParts, 1);

            $value = call_user_func_array($functionName, array_merge([$value], $parameters));
        }

        return $value;
    }

    // ==================== Métodos de renderização de modelos ====================

    /**
     * Substitua todas as variáveis ​​no conteúdo fornecido
     *
     * @param string $content Conteúdo com variáveis ​​para substituir
     * @return string Conteúdo com variáveis ​​substituídas
     */
    protected function substituteVariables(string $content): string
    {
        // Substitua variáveis ​​simples
        $content = str_replace(array_keys($this->values), array_values($this->values), $content);

        // Aplique modificadores a variáveis
        $content = $this->applyVariableModifiers($content);

        // Processar propriedades do objeto
        $content = $this->processObjectProperties($content);

        return $content;
    }

    /**
     * Aplique modificadores a variáveis ​​no conteúdo
     *
     * @param string $content Conteúdo para processar
     * @return string Conteúdo processado
     */
    protected function applyVariableModifiers(string $content): string
    {
        foreach ($this->modifiers as $variableKey => $modifierExpressions) {
            if (strpos($content, "{{" . $variableKey . "|") !== false) {
                
                foreach ($modifierExpressions as $expression) {
                    // Pule as propriedades do objeto (manuseado separadamente)
                    if (
                        strpos($variableKey, "->") === false &&
                        isset($this->values['{{' . $variableKey . '}}'])
                    ) {
                        $modifiedValue = $this->applyModifiers(
                            $this->values['{{' . $variableKey . '}}'],
                            $expression
                        );

                        $content = str_replace('{{' . $expression . '}}', $modifiedValue, $content);
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Processar propriedades do objeto no conteúdo
     *
     * @param string $content Conteúdo para processar
     * @return string Conteúdo processado
     */
    protected function processObjectProperties(string $content): string
    {
        foreach ($this->objectInstances as $varName => $objectInstance) {
            if (!isset($this->objectProperties[$varName])) {
                continue;
            }

            foreach ($this->objectProperties[$varName] as $propertyPath) {
                $fullPattern = "{{" . $varName . $propertyPath;
                
                if (strpos($content, $fullPattern) !== false) {
                    $propertyValue = $this->getObjectPropertyValue($objectInstance, $propertyPath);
                    $content = str_replace("{{" . $varName . $propertyPath . "}}", $propertyValue, $content);

                    // Apply modifiers to object properties
                    $content = $this->applyObjectPropertyModifiers(
                        $content,
                        $varName,
                        $propertyPath,
                        $propertyValue
                    );
                }
            }
        }

        return $content;
    }

    /**
     * Obtenha um valor de propriedade de um objeto
     *
     * @param object $object Instância do objeto
     * @param string $propertyPath Caminho da propriedade (exemplo: "->property->subproperty")
     * @return mixed Property valor
     */
    protected function getObjectPropertyValue(object $object, string $propertyPath): mixed
    {
        $currentValue = '';
        try {
            $currentValue = $object;
            $properties = explode("->", $propertyPath);

            // Pule o primeiro elemento vazio de explode
            for ($i = 1; $i < count($properties); $i++) {
                $property = $properties[$i];

                if ($currentValue === null) {
                    break;
                }

                $normalizedProperty = strtolower(str_replace('_', '', $property));

                // Tente obter valor usando o método de acessador
                if (method_exists($currentValue, "get$normalizedProperty")) {
                    $currentValue = $currentValue->{"get$normalizedProperty"}();
                }
                // Tente o método mágico __get
                elseif (method_exists($currentValue, "__get")) {
                    $currentValue = $currentValue->__get($property);
                }
                // Experimente o acesso direto à propriedade
                elseif (property_exists($currentValue, $normalizedProperty)) {
                    $currentValue = $currentValue->$normalizedProperty;
                } elseif (property_exists($currentValue, $property)) {
                    $currentValue = $currentValue->$property;
                } else {
                    $className = $i > 1 ? $properties[$i - 1] : get_class($object);
                    $currentClass = is_null($currentValue) ? "NULL" : get_class($currentValue);

                    throw new \BadMethodCallException(
                        "No accessor method in class $currentClass for $className->$property"
                    );
                }
            }
        } catch (\BadMethodCallException $ex) {
            echo $ex->getMessage();
        } finally {
            return $currentValue;
        }
    }

    /**
     * Aplicar modificadores às propriedades do objeto
     *
     * @param string $content Conteúdo para processar
     * @param string $varName Nome variável
     * @param string $propertyPath Caminho da propriedade
     * @param mixed $propertyValue Valor da propriedade
     * @return string Conteúdo processado
     */
    protected function applyObjectPropertyModifiers(string $content, string $varName, string $propertyPath, mixed $propertyValue): string
    {
        $modifierKey = $varName . $propertyPath;

        if (isset($this->modifiers[$modifierKey])) {
            foreach ($this->modifiers[$modifierKey] as $modifierExpression) {
                $modifiedValue = $this->applyModifiers($propertyValue, $modifierExpression);
                $content = str_replace('{{' . $modifierExpression . '}}', $modifiedValue, $content);
            }
        }

        return $content;
    }

    /**
     * Mostre um bloco
     *
     * @param string $block O nome do bloco a ser analisado
     * @param boolean $append True Se o conteúdo deve ser anexado
     */
    public function block(string $block, bool $append = true)
    {
        try {
            if (!in_array($block, $this->blocks)) {
                throw new \InvalidArgumentException("block $block does not exist");
            }

            // Process FINALLY blocks for child blocks
            $this->processChildFinallyBlocks($block);

            // Add block content
            $blockValueVar = $block . '_value';
            $blockContent = $this->getVar($block);

            if ($append) {
                $currentValue = $this->getVar($blockValueVar);
                $this->setValue($blockValueVar, $currentValue . $this->substituteVariables($blockContent));
            }

            $this->markBlockAsParsed($block);

            // Clear child blocks
            $this->clearChildBlocks($block);
        } catch (\InvalidArgumentException $ex) {
            echo $ex->getMessage();
        }
    }

    /**
     * Processo finalmente blocos para blocos infantis
     *
     * @param string $block Nome do bloco pai
     */
    protected function processChildFinallyBlocks(string $block)
    {
        if (!isset($this->parentBlocks[$block])) {
            return;
        }

        foreach ($this->parentBlocks[$block] as $childBlock) {
            if (
                isset($this->finallyBlocks[$childBlock]) &&
                !in_array($childBlock, $this->parsedBlocks)
            ) {
                $childValueVar = $childBlock . '_value';
                $finallyContent = $this->finallyBlocks[$childBlock];

                $this->setValue($childValueVar, $this->substituteVariables($finallyContent));
                $this->parsedBlocks[] = $block;
            }
        }
    }

    /**
     * Marque um bloco como analisado
     *
     * @param string $block Nome do bloco
     */
    protected function markBlockAsParsed(string $block)
    {
        if (!in_array($block, $this->parsedBlocks)) {
            $this->parsedBlocks[] = $block;
        }
    }

    /**
     * Clear Child Blocks
     *
     * @param string $block Nome do bloco pai
     */
    protected function clearChildBlocks(string $block)
    {
        if (isset($this->parentBlocks[$block])) {
            foreach ($this->parentBlocks[$block] as $childBlock) {
                $this->clear($childBlock . '_value');
            }
        }
    }

    /**
     * Retorna o conteúdo final analisado
     *
     * @return string O conteúdo do modelo analisado
     */
    public function parse(): string
    {
        $this->parseParentBlocks();
        $this->processFinallyBlocks();

        $content = $this->getVar(".");
        $content = $this->substituteVariables($content);

        // Remova as variáveis ​​indefinidas restantes
        $pattern = "/{{(" . self::REGEX_VALID_NAME . ")((\\-\\>(" . self::REGEX_VALID_NAME . "))*)?" .
            "((\\|.*?)*)?}}/";

        return preg_replace($pattern, "", $content);
    }

    /**
     * Analisar blocos de pais que ainda não foram analisados
     */
    protected function parseParentBlocks()
    {
        foreach (array_reverse($this->parentBlocks, true) as $parent => $children) {
            foreach ($children as $child) {
                if (
                    in_array($parent, $this->blocks) &&
                    in_array($child, $this->parsedBlocks) &&
                    !in_array($parent, $this->parsedBlocks)
                ) {

                    $parentValueVar = $parent . '_value';
                    $parentContent = $this->getVar($parent);

                    $this->setValue($parentValueVar, $this->substituteVariables($parentContent));
                    $this->parsedBlocks[] = $parent;
                }
            }
        }
    }

    /**
     * Processar todos os bloqueios finalmente
     */
    protected function processFinallyBlocks()
    {
        foreach ($this->finallyBlocks as $block => $content) {
            if (!in_array($block, $this->parsedBlocks)) {
                $blockValueVar = $block . '_value';
                $this->setValue($blockValueVar, $this->substituteVariables($content));
            }
        }
    }

    public function enableMinify()
    {
        $this->enabledMinify = true;
    }

    public function optionMinify(array $options)
    {
        $this->optMinify = $options;
    }

    public function show(bool $return = false)
    {
        if ($this->enabledMinify) {
            $minifier = new TinyHtmlMinifier($this->optMinify);
            $parse = $minifier->minify($this->parse());
        } else {
            $parse = $this->parse();
        }

        if ($return) {
            return $parse;
        } else {
            echo $parse;
        }
    }
}
