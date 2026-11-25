<?php
/**
 * Gera a linha de usuário do painel para colar em config/config.php -> 'painel_usuarios'
 * Uso: php bin/criar_usuario.php marcelo 'SenhaForte123'
 * Compatível com PHP 5.6+
 */
if ($argc < 3) {
    fwrite(STDERR, "Uso: php bin/criar_usuario.php <usuario> <senha>\n");
    exit(1);
}
$u = strtolower(trim($argv[1]));
if (!preg_match('/^[a-z0-9._-]{2,40}$/', $u)) {
    fwrite(STDERR, "Usuário inválido (use letras minúsculas, números, ponto, hífen)\n");
    exit(1);
}
if (strlen($argv[2]) < 8) {
    fwrite(STDERR, "Senha muito curta (mínimo 8 caracteres)\n");
    exit(1);
}
echo "        '" . $u . "' => '" . password_hash($argv[2], PASSWORD_DEFAULT) . "',\n";
