<?php declare(strict_types = 1);

namespace Phpdcsd\Rules;

#autoload classes
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use PhpParser\Node\Stmt\Property;
use ReflectionClass;

/**
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Stmt\Class_>
 */
class Nmoreonequeries implements \PHPStan\Rules\Rule #class que implementa a interface Rule do PHPStan
{


    private $method; // declaração de array de métodos
    private $lineMethod; // declaração de array de linhas de métodos
    private $lineForeach; // declaração de array de linhas de foreach

    public function __construct()
    {
        // Inicialize $entidades como um array vazio no construtor
        $this->method = ""; // inicialização de array de métodos
        $this->lineMethod = 0; // inicialização de array de linhas de métodos
        $this->lineForeach = 0; // inicialização de array de linhas de foreach
    }
    #função que verifica se a classe tem mais de um método que faz query
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {

        $className = $node->name->name;
        $methods = $node->getMethods();


        # verifica se é um controller

        if ($this->isControllerClass($node)) {
            $entityName = substr($className, 0, -10);

            $hasFindAllWithForeach = $this->hasFindAllWithForeach($node);

            if ($hasFindAllWithForeach) {

                $solutions = [
                    "I - Utilizar carregamento ansioso (eager loading) para relacionamentos necessários.",
                    "II - Escrever consultas personalizadas com DQL (Doctrine Query Language) para obter dados de forma otimizada.",
                    "III - Certificar-se de que relacionamentos um-para-muitos (OneToMany) com coleções sejam definidos corretamente, estabelecendo associações bidirecionais quando necessário. \n",
                ];

                $errorMessage = RuleErrorBuilder::message(sprintf(
                    "No método $this->method do controller $className, podem estar ocorrendo múltiplas consultas ao banco de dados. O Controller $className contém um loop foreach na linha $this->lineForeach, no método $this->method. Isso pode indicar a presença de um possível problema de N+1 queries.
                    \nPossíveis Soluções:\n\n%s",
                    implode("\n", $solutions)
                ))
                ->line($this->lineMethod)
                ->identifier('n_plus_one_queries_problem')
                ->build();

                return [$errorMessage];
            }
        }

        # verifica se é uma entidade

        if($this->isEntityClass($node)){
            # encontrar relacionameto OneToMany em uma entidade e vertificar se a entidade relacionada tem o relacionamento ManyToOne com a entidade atual

            $namespace = $node->namespacedName->toString();
            $className = $node->name->name;
            $reflectionClass = new \ReflectionClass($namespace);

            $properties = $reflectionClass->getProperties(); // Pega as propriedades da classe
            $parametrosOneToMany = ["targetEntity", "mappedBy"]; // declaração de array de parâmetros OneToMany necessários
            $parametrosManyToOne = ["inversedBy"]; // declaração de array de parâmetros ManyToOne necessários

                foreach($properties as $properti){
                $propertyName = $properti->getName(); // Nome da propriedade
                $annotations = $properti->getAttributes(); // Pega as anotações da propriedade
                $lineProperty = $this->getLineProperty($node,$propertyName); // Pega a linha da propriedade

                foreach($annotations as $annotation){

                    $annotationName = $annotation->getName(); // Nome da anotação
                    $annotationParameters = $annotation->getArguments(); // Parâmetros da anotação

                    // Verifica se a anotação é OneToMany
                    if ($annotationName == "Doctrine\ORM\Mapping\OneToMany") {

                        //se não tiver os parâmetros necessários, retorna erro
                        foreach ($parametrosOneToMany as $parametro) {
                            if (!array_key_exists($parametro, $annotationParameters)) {
                                echo "O parâmetro $parametro é necessário na anotação OneToMany \n";

                                #buiilderError
                                $errorMessage = RuleErrorBuilder::message(sprintf(
                                    "\nPossível problema de N+1 queries devido a um relacionamento unilateral.\nA anotação OneToMany na classe de entidade '%s' deve incluir o parâmetro '%s' na propriedade '%s'.\n",
                                    $className,
                                    $parametro,
                                    $propertyName
                                ))
                                    ->line($lineProperty)
                                    ->identifier('n_plus_one_queries_problem')
                                    ->build();

                                    return [$errorMessage];
                            }
                        }
                    }

                    // Verifica se a anotação é ManyToOne
                    if ($annotationName == "Doctrine\ORM\Mapping\ManyToOne") {

                        //se não tiver os parâmetros necessários, retorna erro
                        foreach ($parametrosManyToOne as $parametro) {
                            if (!array_key_exists($parametro, $annotationParameters)) {
                                echo "O parâmetro $parametro é necessário na anotação ManyToOne \n";

                                #buiilderError
                                $errorMessage = RuleErrorBuilder::message(sprintf(
                                    "Possível problema de N+1 queries devido a um relacionamento unilateral.\nA anotação ManyToOne na classe de entidade '%s' deve incluir o parâmetro '%s' na propriedade '%s'.\n",

                                    $className,
                                    $parametro,
                                    $propertyName
                                ))
                                    ->line($lineProperty)
                                    ->identifier('n_plus_one_queries_problem')
                                    ->build();

                                    return [$errorMessage];
                            }
                        }
                    }
                }

            }


        }


        return [];

    } #fim da função processNode

    #função que verifica se a classe é uma entidade
    private function isEntityClass(Node\Stmt\Class_ $node): bool
    {
        #$className = $node->name->name;
        $namespace = $node->namespacedName->toString();
        if (strpos($namespace, "\Entity") !== false) {
            return true; // Substitua por sua lógica real
        }

        return false;
    }

    private function getLineProperty($node,$propertyname)
    {
        $properties = $node->getProperties();
        foreach ($properties as $property) {
            $propertyName = $property->props[0]->name->name;
            if ($propertyName == $propertyname) {
                $line = $property->getStartLine();
                return $line;
            }
        }
    }

    #função que verifica se a classe tem eager
    private function hasEagerAnnotation(Node\Stmt\Class_ $classNode, $file_path): bool
    {

        $file = file_get_contents($file_path);

        if (strpos($file, 'EAGER') !== false) {
            return true;
        }
        return false; // Substitua por sua lógica real
    }

    #função que verifica se a classe é um controller
    private function isControllerClass(Node\Stmt\Class_ $node): bool
    {
        $className = $node->name->name;
        $namespace = $node->namespacedName->toString();

        if (strpos($namespace, "\Controller") !== false) {

            return true;
        }

        return false;
    }

    private function hasFindAllWithForeach($node)
    {

        $methods = $node->getMethods(); #Array de métodos da classe
        $controllerName = $node->name->name;

        echo "controller NAME: $controllerName \n";
        echo "_______________________________________ \n";

        foreach ($methods as $method) {
            echo "Metodo do controller:". $controllerName .  " | methodo = $method->name  \n";
            echo "_______________________________________ \n";
            // Verifique se o método contém declarações (stmts)
            if ($method->getStmts() === null) {
                continue;
            }

            // Inicialize uma variável para rastrear se uma consulta foi encontrada
            $queryFound = false;
            $foreachFound = false;

            foreach ($method->getStmts() as $stmt) {

                #verifico se o stmt é uma expressão e se a expressão é uma atribuição
                if ($stmt instanceof Node\Stmt\Expression && $stmt->expr instanceof Node\Expr\Assign)
                {
                    $assignExpr = $stmt->expr->expr; #pego a expressão da atribuição
                    #verifico se a expressão da atribuição é uma chamada de método
                    if ($assignExpr instanceof Node\Expr\MethodCall) {

                        $desiredMethods = ["findAll", "findBy"];
                        #verifico se o nome do método é um dos métodos desejados
                        if (in_array($assignExpr->name->name, $desiredMethods)) {
                            $queryFound = true; #se for, seto a variável para true
                            $linha_query = $stmt->getStartLine();
                            echo "query encontrada na linha: $linha_query \n";

                        }
                    }

                }

                // Verifique se há um loop foreach

                if ($stmt instanceof Node\Stmt\Foreach_) {
                    $foreachFound = true;
                    $linha_foreach = $stmt->getStartLine();
                    $met = $method->name;
                    echo "foreach encontrado na linha: $linha_foreach do metodo: $met \n";

                }

            }

            if ($queryFound && $foreachFound) {
                $linha_metodo = $method->getStartLine()+1;
                echo "linha do metodo: $linha_metodo \n";
                echo "linha do foreach: $linha_foreach \n";
                $this->lineMethod = $linha_metodo;
                $this->lineForeach = $linha_foreach;
                $this->method = $method->name;
                $mensage = "Possivel Code Smell N+1 Query: no controller " . $controllerName . " no método " . $method->name . " foi encontrado um loop foreach com uma query dentro \n";
                echo $mensage;
                #retornar o erro

                return true;
            }
        }

    } #fim da função hasFindAllWithForeach

} #fim da classe Nmoreonequeries
