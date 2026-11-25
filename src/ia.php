<?php
// Análise de IA das conversas com nota baixa de CSAT
// Compatível com PHP 5.6+

/** Busca TODAS as mensagens de uma conversa (API devolve em blocos; pagina com "before") */
function buscarMensagens($conversa)
{
    $todas = array();
    $antes = null;
    for ($i = 0; $i < 50; $i++) {
        $q = $antes ? array('before' => $antes) : array();
        $lote = lista(api('/conversations/' . (int)$conversa . '/messages', $q));
        if (!$lote) {
            break;
        }
        $menor = null;
        foreach ($lote as $m) {
            $todas[(int)$m['id']] = $m;
            if ($menor === null || (int)$m['id'] < $menor) {
                $menor = (int)$m['id'];
            }
        }
        if ($menor === null || $menor === $antes) {
            break;
        }
        $antes = $menor;
        usleep(150000);
    }
    ksort($todas);
    return array_values($todas);
}

/** Remove dados pessoais antes de mandar para a IA */
function mascarar($t)
{
    $t = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $t);
    $t = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', '[cpf]', $t);
    $t = preg_replace('/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/', '[cnpj]', $t);
    $t = preg_replace('/\(?\+?\d[\d\s().-]{7,}\d/', '[telefone]', $t);
    return $t;
}

/** Monta a transcrição legível da conversa */
function transcricao($msgs)
{
    $linhas = array();
    foreach ($msgs as $m) {
        if (g($m, 'content_type') === 'input_csat') {
            continue;
        }
        $tipo     = (int)g($m, 'message_type', 0);
        $conteudo = trim((string)g($m, 'content', ''));
        if ($conteudo === '' && g($m, 'attachments')) {
            $conteudo = '[anexo]';
        }
        if ($conteudo === '') {
            continue;
        }

        $tipoRemetente = g($m, 'sender.type', g($m, 'sender_type', ''));
        $nome = g($m, 'sender.name', '');
        if ($tipo === 0) {
            $quem = 'CLIENTE';
        } elseif ($tipo === 2) {
            $quem = 'SISTEMA';
        } elseif ($tipo === 3) {
            $quem = 'MENSAGEM AUTOMÁTICA';
        } elseif (stripos($tipoRemetente, 'bot') !== false) {
            $quem = 'IA/BOT' . ($nome ? " ($nome)" : '');
        } else {
            $quem = 'ATENDENTE' . ($nome ? " ($nome)" : '');
        }
        if (g($m, 'private')) {
            $quem .= ' [nota interna]';
        }

        $quando = g($m, 'created_at') ? date('d/m H:i', strtotime(ts(g($m, 'created_at')))) : '';
        $linhas[] = "[$quando] $quem: $conteudo";
    }

    $txt = mascarar(implode("\n", $linhas));
    $max = 24000;
    if (strlen($txt) > $max) { // mantém o começo e o fim (onde costuma estar o problema e o desfecho)
        $txt = substr($txt, 0, 4000) . "\n[... trecho do meio omitido ...]\n" . substr($txt, -($max - 4000));
    }
    return $txt;
}

function promptSistema()
{
    return <<<TXT
Você é um auditor de qualidade do suporte de uma empresa de software para clínicas (sistema iGUT).
Vai receber a transcrição de um atendimento em que o cliente deu NOTA BAIXA na pesquisa de satisfação (CSAT, escala 1 a 5).
Os atendimentos podem envolver a IA "Guta" (bot) e atendentes humanos.

Analise e responda SOMENTE com um JSON válido, sem texto antes ou depois, neste formato:
{
  "motivo_principal": "frase curta e objetiva com o motivo mais provável da nota baixa",
  "categoria": "uma de: demora_resposta | problema_nao_resolvido | erro_ou_bug_sistema | comunicacao_atendente | informacao_incorreta | transferencias_excessivas | insatisfacao_regra_ou_preco | atendimento_ia_guta | expectativa_do_cliente | outro",
  "responsavel": "uma de: atendente | ia_guta | produto_sistema | processo_regra | cliente | indefinido",
  "gravidade": "baixa | media | alta",
  "resumo": "2 a 4 frases sobre o que aconteceu no atendimento",
  "evidencias": ["até 3 trechos curtos da conversa que sustentam a análise"],
  "sugestao_melhoria": "ação concreta para o supervisor/pós-vendas",
  "confianca": 0.0
}

Regras:
- Baseie-se apenas na conversa e no comentário do cliente. Se não houver evidência suficiente, use "indefinido" e confiança baixa.
- Seja justo: nota baixa pode ser culpa de regra/produto e não do atendente.
- Escreva em português do Brasil.
TXT;
}

/** Chama o provedor de IA configurado e devolve o texto da resposta */
function chamarIA($sistema, $usuario)
{
    $prov  = cfg('ia_provider') ? cfg('ia_provider') : 'anthropic';
    $model = cfg('ia_model');
    $key   = cfg('ia_api_key');

    if ($prov === 'openai') {
        $url  = (cfg('ia_base_url') ? cfg('ia_base_url') : 'https://api.openai.com') . '/v1/chat/completions';
        $hdr  = array('Authorization: Bearer ' . $key, 'Content-Type: application/json');
        $body = array(
            'model' => $model, 'temperature' => 0.2,
            'response_format' => array('type' => 'json_object'),
            'messages' => array(
                array('role' => 'system', 'content' => $sistema),
                array('role' => 'user', 'content' => $usuario),
            ),
        );
    } else {
        $url  = (cfg('ia_base_url') ? cfg('ia_base_url') : 'https://api.anthropic.com') . '/v1/messages';
        $hdr  = array('x-api-key: ' . $key, 'anthropic-version: 2023-06-01', 'Content-Type: application/json');
        $body = array(
            'model' => $model, 'max_tokens' => 1200, 'temperature' => 0.2,
            'system' => $sistema,
            'messages' => array(array('role' => 'user', 'content' => $usuario)),
        );
    }

    for ($tent = 0; $tent < 4; $tent++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => $hdr,
            CURLOPT_POSTFIELDS     => j($body),
        ));
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code === 200) {
            $r = json_decode($resp, true);
            $txt = ($prov === 'openai') ? g($r, 'choices.0.message.content') : g($r, 'content.0.text');
            if ($txt === null) {
                throw new RuntimeException('Resposta da IA sem conteúdo');
            }
            return $txt;
        }
        if ($code === 429 || $code >= 500 || $code === 0) {
            sleep(pow(2, $tent) * 2);
            continue;
        }
        throw new RuntimeException("IA HTTP $code: " . substr((string)$resp, 0, 300) . " $err");
    }
    throw new RuntimeException('IA indisponível após várias tentativas');
}

/** Extrai e valida o JSON devolvido pela IA */
function interpretarIA($txt)
{
    $ini = strpos($txt, '{');
    $fim = strrpos($txt, '}');
    if ($ini === false || $fim === false) {
        throw new RuntimeException('IA não devolveu JSON');
    }
    $d = json_decode(substr($txt, $ini, $fim - $ini + 1), true);
    if (!is_array($d) || !g($d, 'motivo_principal')) {
        throw new RuntimeException('JSON da IA inválido');
    }
    $ev = g($d, 'evidencias', array());
    $d['evidencias_txt'] = is_array($ev) ? implode("\n", array_map('strval', $ev)) : (string)$ev;
    $c = (float)g($d, 'confianca', 0);
    $d['confianca'] = max(0, min(1, $c));
    return $d;
}
