<?php declare(strict_types = 1);

namespace Phpdcsd\Rules;


use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;


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

        $entity_has_eager = false;
        $className = $node->name->name;
        $methods = $node->getMethods();

        # verifica se é um controller
        if ($this->isControllerClass($node)) {
            $entityName = substr($className, 0, -10);

            $hasFindAllWithForeach = $this->hasFindAllWithForeach($node);

            if ($hasFindAllWithForeach) {

                echo "$this->lineForeach \n";
                echo "$this->lineMethod \n";
                echo "$this->method \n";
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


             /*
        # verifica se é uma entidade
        if($this->isEntityClass($node)){
            # verifica se a entidade tem eager

            if($this->hasEagerAnnotation($node, $scope->getFile())){
                $entity_has_eager = true;
            }
                #pegar o controller da entidade antes de criar a entidade

                $controller = null;

                foreach($this->controllers as $c){
                    if($c->entityName == $className){
                        $controller = $c;
                    }
                }
                #criar a entidade e adicionar no array de entidades
                if ($controller !== null){
                    $classEntity = new ClassEntity($className, $methods, $entity_has_eager, $controller);
                }

                #atuaizar também o controller

                #$controller->setEntity($classEntity);

                #atuializar o array de entidades
                $this->entidades[] = $classEntity;


        }   */


        return [];

    } #fim da função processNode

    #função que verifica se a classe é uma entidade
    private function isEntityClass(Node\Stmt\Class_ $node): bool
    {
        $className = $node->name->name;
        if ($node->namespacedName->toString() == "App\Entity\\".$className)
        {
            return true; // Substitua por sua lógica real
        }

        return false;
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
        if ($node->namespacedName->toString() == "App\Controller\\".$className)
        {
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
