# PHPDCS - Detecção Code Smells Symfony Doctrine (EM DESENVOLVIMENTO)
## Analisar codigo estico php symfony/ doctrine

## identificar code smells, tais como:

- Problema de N+1: Consultas que resultam em um grande número de consultas adicionais devido ao carregamento lazy de relacionamentos.

- Carregamento Ansioso Excessivo (Eager Loading): Consultas que trazem mais dados do que o necessário, resultando em sobrecarga de rede e uso excessivo de memória.

- Consultas Ineficientes: Consultas que não são otimizadas ou que poderiam ser combinadas para melhorar o desempenho.



# Como Executar

1. Instale a biblioteca em seu projeto Symfony usando o Composer:

    ````
    composer require kleitomberg/phpdcsd
    ````
2. Execute os testes da biblioteca para verificar a detecção de code smells:


    ````
    composer run-test 
    ````