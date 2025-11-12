<?php
// Redireciona para a página de login principal, que agora contém ambos os formulários.
// O JavaScript na página de login cuidará de mostrar o formulário correto.
header('Location: login.php#register'); // O hash é opcional, mas pode ser útil.
exit;