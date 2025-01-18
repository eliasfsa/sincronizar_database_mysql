<?php

// Função para verificar se a tabela existe
function tabelaExiste($conexao, $tabela) {
    $stmt = $conexao->prepare("SHOW TABLES LIKE :tabela");
    $stmt->bindParam(':tabela', $tabela);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

// Função para criar a tabela se não existir
function criarTabelaSeNaoExistir($conexao, $sql) {
    try {
        $stmt = $conexao->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "Erro ao criar tabela: " . $e->getMessage();
        return false;
    }
}

// Função para copiar dados de uma tabela
function copiarDados($conexaoOrigem, $conexaoDestino, $tabela) {
    try {
        // Verifica se a tabela existe na origem
        $stmtOrigem = $conexaoOrigem->query("SELECT * FROM $tabela");
        $dados = $stmtOrigem->fetchAll(PDO::FETCH_ASSOC);

        if (count($dados) > 0) {
            // Cria uma lista de campos para inserção
            $colunas = array_keys($dados[0]);
            $colunasStr = implode(", ", $colunas);
            $valoresStr = implode(", ", array_fill(0, count($colunas), "?"));
            
            $stmtDestino = $conexaoDestino->prepare("INSERT IGNORE INTO $tabela ($colunasStr) VALUES ($valoresStr)");

            foreach ($dados as $linha) {
                $stmtDestino->execute(array_values($linha));
            }
            return true;
        }
    } catch (PDOException $e) {
        echo "Erro ao copiar dados da tabela `$tabela`: " . $e->getMessage();
        echo "<span style='color: red;'>Erro ao copiar dados da tabela `$tabela`:</span><br>". $e->getMessage(); 
        return false;
    }
}

// Função para listar tabelas
function listarTabelas($conexao) {
    $stmt = $conexao->query("SHOW TABLES");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Função para realizar a sincronização
function sincronizar($conexaoOrigem, $conexaoDestino, $tabelasSelecionadas, $sincronizarDados) {
    foreach ($tabelasSelecionadas as $tabela) {
        echo "Sincronizando a tabela $tabela...<br>";
        
        // Verifica se a tabela já existe no banco de dados de destino
        if (!tabelaExiste($conexaoDestino, $tabela)) {
            // Recupera a definição da tabela do banco de dados de origem
            $stmtOrigem = $conexaoOrigem->query("SHOW CREATE TABLE $tabela");
            $criarTabelaSQL = $stmtOrigem->fetch(PDO::FETCH_ASSOC)['Create Table'];

            // Tenta criar a tabela no banco de dados de destino
            if (criarTabelaSeNaoExistir($conexaoDestino, $criarTabelaSQL)) {
                echo "<span style='color: blue;'>Tabela `$tabela` criada com sucesso.</span><br>";
                
            } else {
                echo "<span style='color: red;'>Erro: Tabela `$tabela` não pôde ser criada.</span><br>";
                continue;
            }
        }

        // Sincronizar dados, se necessário
        if ($sincronizarDados) {
            if (copiarDados($conexaoOrigem, $conexaoDestino, $tabela)) {
               echo "<span style='color: blue;'>Dados da Tabela `$tabela` copiados com sucesso.</span><br>";
            }
        }
    }
}

// Função para exibir o menu de opções (via formulário HTML)
function exibirMenu() {
    if (isset($_POST['opcao'])) {
        return $_POST['opcao'];
    }
    return null;
}

// Função para escolher tabelas específicas (via formulário HTML)
function escolherTabelas($tabelas) {
    if (isset($_POST['tabelas'])) {
        return $_POST['tabelas'];
    }
    return [];
}

// Função para configurar as conexões (via formulário HTML)
function configurarConexoes() {
    if (isset($_POST['conexao_origem']) && isset($_POST['conexao_destino'])) {
        return [
            'origem' => $_POST['conexao_origem'],
            'destino' => $_POST['conexao_destino']
        ];
    }
    return [
        'origem' => ['host' => 'localhost', 'dbname' => 'origen_db', 'user' => 'root', 'pass' => '1234'],
        'destino' => ['host' => 'localhost', 'dbname' => 'destino_db', 'user' => 'root', 'pass' => '1234']
    ];
}

// Exemplo de uso
// Aqui, os dados de conexão podem ser fornecidos via POST ou mantidos como padrão se o formulário não for enviado
$conexaoConfig = configurarConexoes();
$conexaoOrigem = new PDO('mysql:host=' . $conexaoConfig['origem']['host'] . ';dbname=' . $conexaoConfig['origem']['dbname'], $conexaoConfig['origem']['user'], $conexaoConfig['origem']['pass']);
$conexaoDestino = new PDO('mysql:host=' . $conexaoConfig['destino']['host'] . ';dbname=' . $conexaoConfig['destino']['dbname'], $conexaoConfig['destino']['user'], $conexaoConfig['destino']['pass']);

// Listar todas as tabelas no banco de dados de origem
$tabelas = listarTabelas($conexaoOrigem);

// Exibir o menu para o usuário escolher
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opcao = exibirMenu();

    if ($opcao == '1') {
        // Sincronizar todas as tabelas
        echo "<h2>Sincronizando todas as tabelas...</h2>";
        sincronizar($conexaoOrigem, $conexaoDestino, $tabelas, true);
    } elseif ($opcao == '2') {
        // Sincronizar tabelas selecionadas
        $tabelasEscolhidas = escolherTabelas($tabelas);
        sincronizar($conexaoOrigem, $conexaoDestino, $tabelasEscolhidas, true);
    } else {
    echo "<span style='color: red;'>Opção inválida! Tente novamente..</span>";
    }
} else {
    // Exibir o formulário com layout melhorado
    ?>
    <form method="POST">
        <label>Escolha uma opção de sincronização:</label><br>
        <input type="radio" name="opcao" value="1" id="opcao1" required>
        <label for="opcao1">Sincronizar todas as tabelas</label><br>

        <input type="radio" name="opcao" value="2" id="opcao2" required>
        <label for="opcao2">Sincronizar tabelas selecionadas</label><br>

        <div class="row">
            <!-- Conexão de Origem -->
            <div class="col-md-6">
                <h4>Conexão de Origem</h4>
                <div class="mb-3">
                    <label for="host_origem" class="form-label">Servidor</label>
                    <input type="text" class="form-control" name="conexao_origem[host]" id="host_origem" value="<?= $conexaoConfig['origem']['host'] ?>" required>
                </div>
                <div class="mb-3">
                    <label for="dbname_origem" class="form-label">Banco de Dados</label>
                    <input type="text" class="form-control" name="conexao_origem[dbname]" id="dbname_origem" value="<?= $conexaoConfig['origem']['dbname'] ?>" required>
                </div>
                <div class="mb-3">
                    <label for="user_origem" class="form-label">Usuário</label>
                    <input type="text" class="form-control" name="conexao_origem[user]" id="user_origem" value="<?= $conexaoConfig['origem']['user'] ?>" required>
                </div>
                <div class="mb-3">
                    <label for="pass_origem" class="form-label">Senha</label>
                    <input type="password" class="form-control" name="conexao_origem[pass]" id="pass_origem" value="<?= $conexaoConfig['origem']['pass'] ?>" required>
                </div>
            </div>

            <!-- Conexão de Destino -->
            <div class="col-md-6">
                <h4>Conexão de Destino</h4>
                <div class="mb-3">
                    <label for="host_destino" class="form-label">Servidor</label>
                    <input type="text" class="form-control" name="conexao_destino[host]" id="host_destino" value="<?= $conexaoConfig['destino']['host'] ?>" required>
                </div>
                <div class="mb-3">
                    <label for="dbname_destino" class="form-label">Banco de Dados</label>
                    <input type="text" class="form-control" name="conexao_destino[dbname]" id="dbname_destino" value="<?= $conexaoConfig['destino']['dbname'] ?>" required>
                </div>
                <div class="mb-3">
                    <label for="user_destino" class="form-label">Usuário</label>
                    <input type="text" class="form-control" name="conexao_destino[user]" id="user_destino" value="<?= $conexaoConfig['destino']['user'] ?>" required>
                </div>
                <div class="mb-3">
                    <label for="pass_destino" class="form-label">Senha</label>
                    <input type="password" class="form-control" name="conexao_destino[pass]" id="pass_destino" value="<?= $conexaoConfig['destino']['pass'] ?>" required>
                </div>
            </div>
        </div>

        <div id="tabelas_selecionadas" style="display:none;">
            <label for="tabelas">Escolha as tabelas a serem sincronizadas:</label><br>
            <?php foreach ($tabelas as $tabela) : ?>
                <input type="checkbox" name="tabelas[]" value="<?= $tabela ?>" id="<?= $tabela ?>">
                <label for="<?= $tabela ?>"><?= $tabela ?></label><br>
            <?php endforeach; ?>
        </div>

        <input type="submit" class="btn btn-primary" value="Enviar">
    </form>

    <script>
        // Exibe a seleção das tabelas quando a opção 2 é escolhida
        document.querySelectorAll('input[name="opcao"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                document.getElementById('tabelas_selecionadas').style.display = (this.value == '2') ? 'block' : 'none';
            });
        });
    </script>
    <?php
}
?>
