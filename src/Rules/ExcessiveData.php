<?php

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
class ExcessiveData implements \PHPStan\Rules\Rule #class que implementa a interface Rule do PHPStan
{



    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->isEntityClass($node)) {
            #echo "A classe $node->name->name é uma entidade\n";
            $namespace = $node->namespacedName->toString();
            $className = $node->name->name;
            $reflectionClass = new \ReflectionClass($namespace);

            $properties = $reflectionClass->getProperties(); // Pega as propriedades da classe
            $paramsDesejado = ['fetch'];

            foreach($properties as $properti){
                $propertyName = $properti->getName(); // Nome da propriedade
                #echo "A propriedade $propertyName da classe $className \n";
                $annotations = $properti->getAttributes(); // Pega as anotações da propriedade
                $lineProperty = $this->getLineProperty($node,$propertyName);

                foreach($annotations as $annotation){

                    $annotationName = $annotation->getName(); // Nome da anotação
                    $annotationParameters = $annotation->getArguments(); // Parâmetros da anotação

                    // Verifica se a anotação ManyToOne e tem fetch = EAGER
                    echo " Nome da annotation: $annotationName \n";
                    echo "__________________________________________________________\n";
                    if($annotationName == "Doctrine\ORM\Mapping\ManyToOne" || $annotationName == "Doctrine\ORM\Mapping\OneToOne" || $annotationName == "Doctrine\ORM\Mapping\OneToMany"){

                        foreach($annotationParameters as $annotationParameter => $annotationValue){

                            echo " Nome do parâmetro da annotation: $annotationParameter \n";
                            echo " Valor do parâmetro da annotation: $annotationValue \n";
                            echo "__________________________________________________________\n";

                            if ($annotationParameter == "fetch" && $annotationValue == "EAGER") {

                                #buiilderError
                                $sugestões = [
                                    "\n\n1 - Remover o parâmetro fetch da anotação $annotationName da propriedade $propertyName da classe $className na linha $lineProperty.",
                                    "2 - Alterar o parâmetro fetch da anotação $annotationName da propriedade $propertyName da classe $className na linha $lineProperty para LAZY.",
                                    "3 - Criar uma consulta personalizada no repositório da classe $className utilizando DQL para buscar apenas os dados necessários. \n"
                                ];
                                $errorMessage = RuleErrorBuilder::message(sprintf(
                                    'A classe %s possui a propriedade %s com a anotação %s e o parâmetro %s = %s na linha %s, o que pode causar excesso de dados. Considere remover o parâmetro fetch ou alterá-lo para LAZY para evitar o carregamento excessivo de dados. Caso precise de mais informações ou sugestões de refatoração, recomendamos as seguintes ações:\n%s\n',
                                    $className,
                                    $propertyName,
                                    $annotationName,
                                    $annotationParameter,
                                    $annotationValue,
                                    $lineProperty,
                                    implode("\n \n", $sugestões)
                                ))->build();

                                return [$errorMessage];

                            }
                        }

                    }//if

                }

            }


        }

    return [];

    }

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


}
