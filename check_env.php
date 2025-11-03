<?php
declare(strict_types=1);

echo "<h1>Diagnóstico do Ambiente</h1>";

echo "<h2>Versão do PHP</h2>";
echo "<p>Versão atual: <strong>" . PHP_VERSION . "</strong></p>";
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    echo "<p style='color: red; font-weight: bold;'>Atenção: Sua versão do PHP é muito antiga. A biblioteca do MongoDB requer PHP 7.4 ou superior.</p>";
} else {
    echo "<p style='color: green; font-weight: bold;'>OK: Versão do PHP é compatível.</p>";
}

echo "<h2>Extensão MongoDB</h2>";
if (extension_loaded('mongodb')) {
    echo "<p style='color: green; font-weight: bold;'>OK: A extensão 'mongodb' está carregada.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>ERRO CRÍTICO: A extensão 'mongodb' NÃO está carregada. Verifique seu arquivo php.ini.</p>";
}

echo "<h2>Autoloader do Composer</h2>";
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    echo "<p style='color: green; font-weight: bold;'>OK: O arquivo 'vendor/autoload.php' foi encontrado.</p>";
    require_once $autoloadPath;
    echo "<p style='color: green; font-weight: bold;'>OK: O autoloader foi incluído com sucesso.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>ERRO CRÍTICO: O arquivo 'vendor/autoload.php' NÃO foi encontrado no caminho esperado. Execute 'composer install'.</p>";
}
?>