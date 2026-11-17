<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

session_start();

const WHATSAPP_NUMBER = '5522999315667';
const MAPS_URL = 'https://www.google.com/maps/search/?api=1&query=Vertice+Fitness+Academia+de+Luxo+Av.+Atlantica+1900+Copacabana+Rio+de+Janeiro';
const UNIT_ADDRESS = 'Av. Atlântica, 1900 — Copacabana, Rio de Janeiro · Torre Vértice, 3º andar';
const LEADS_FILE = __DIR__ . '/data/leads.json';

$plans = [
    ['id' => 'black', 'title' => 'Black', 'price' => 'R$ 449/mês', 'detail' => 'Musculação · bike indoor · aulas coletivas · app Vértice · avaliação inicial'],
    ['id' => 'gold', 'title' => 'Gold', 'price' => 'R$ 789/mês', 'detail' => 'Tudo do Black + personal 4x/mês · spa recovery · nutricionista · bike reservada'],
    ['id' => 'platinum', 'title' => 'Platinum', 'price' => 'R$ 1.290/mês', 'detail' => 'Tudo do Gold + personal ilimitado · concierge · lounge VIP · prioridade no cycling'],
];

$services = [
    ['title' => 'Musculação (piso premium Technogym)', 'price' => 'incluso nos planos'],
    ['title' => 'Bike indoor / cycling studio', 'price' => 'incluso nos planos · avulsa R$ 95'],
    ['title' => 'Functional & HIIT VIP', 'price' => 'incluso nos planos · avulsa R$ 110'],
    ['title' => 'Personal Training privado', 'price' => 'a partir de R$ 280/sessão'],
    ['title' => 'Studio Pilates Reformer', 'price' => 'R$ 190/aula'],
    ['title' => 'Spa & Recovery (crio + massagem)', 'price' => 'R$ 320/sessão'],
    ['title' => 'Assessoria nutricional premium', 'price' => 'R$ 650/mês'],
    ['title' => 'Day Pass convidado', 'price' => 'R$ 180/dia'],
];

$scheduleSlots = [
    ['id' => 'musculacao', 'label' => 'Amanhã · 07:00 — Musculação (orientação)'],
    ['id' => 'bike', 'label' => 'Amanhã · 08:30 — Bike indoor'],
    ['id' => 'tarde', 'label' => 'Amanhã · 18:30 — Functional VIP'],
    ['id' => 'noite', 'label' => 'Quinta · 20:00 — Pilates Reformer'],
];

$mainMenu = [
    ['id' => 'planos', 'label' => 'Planos e valores'],
    ['id' => 'servicos', 'label' => 'Serviços da unidade'],
    ['id' => 'agendar', 'label' => 'Agendar treino'],
    ['id' => 'horarios', 'label' => 'Horários de funcionamento'],
    ['id' => 'cadastro', 'label' => 'Cadastro de aluno'],
    ['id' => 'maps', 'label' => 'Como chegar (Google Maps)', 'accent' => true],
    ['id' => 'atendente', 'label' => 'Falar com atendente (WhatsApp)', 'accent' => true],
];

function state(): array
{
    if (!isset($_SESSION['vertice']) || !is_array($_SESSION['vertice'])) {
        $_SESSION['vertice'] = [
            'step' => 'askName',
            'userName' => '',
            'draftStudent' => ['name' => '', 'phone' => ''],
        ];
    }
    return $_SESSION['vertice'];
}

function saveState(array $s): void
{
    $_SESSION['vertice'] = $s;
}

function greetingName(array $s): string
{
    return $s['userName'] !== '' ? 'Olá, ' . $s['userName'] : 'Olá';
}

function menuOptions(): array
{
    global $mainMenu;
    return $mainMenu;
}

function scheduleOptions(): array
{
    global $scheduleSlots;
    $opts = array_map(static fn(array $s): array => ['id' => $s['id'], 'label' => $s['label']], $scheduleSlots);
    $opts[] = ['id' => 'menu', 'label' => 'Voltar ao menu'];
    return $opts;
}

function reply(string $text, array $extra = []): array
{
    return array_merge(['text' => $text], $extra);
}

function saveLead(array $student): void
{
    $dir = dirname(LEADS_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $leads = [];
    if (is_file(LEADS_FILE)) {
        $raw = file_get_contents(LEADS_FILE);
        $decoded = json_decode($raw ?: '[]', true);
        if (is_array($decoded)) {
            $leads = $decoded;
        }
    }
    $leads[] = [
        'name' => $student['name'],
        'phone' => $student['phone'],
        'created_at' => date('c'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ];
    file_put_contents(LEADS_FILE, json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function handleBoot(): array
{
    $s = state();
    $s['step'] = 'askName';
    $s['userName'] = '';
    $s['draftStudent'] = ['name' => '', 'phone' => ''];
    saveState($s);

    return [
        'messages' => [
            reply(
                "Bem-vindo(a) à Vértice Fitness.\n\nPara começarmos, qual é o seu nome?",
                ['media' => true]
            ),
        ],
        'step' => 'askName',
    ];
}

function showMainMenu(array &$s, ?string $intro = null): array
{
    $s['step'] = 'menu';
    return reply(
        $intro ?? (greetingName($s) . ", como posso ajudar hoje?\nEscolha uma opção abaixo:"),
        ['options' => menuOptions()]
    );
}

function handleOption(string $id, array &$s): array
{
    global $plans, $services, $scheduleSlots;

    $messages = [];

    if ($id === 'planos') {
        $lines = array_map(
            static fn(array $p): string => "• {$p['title']} — {$p['price']}\n  {$p['detail']}",
            $plans
        );
        $messages[] = reply(
            "Nossos planos Vértice Fitness:\n\n" . implode("\n\n", $lines) . "\n\nTaxa de adesão: R$ 190 (isentas no Platinum)."
        );
        $messages[] = showMainMenu($s, 'Deseja ver outra opção?');
        return ['messages' => $messages, 'step' => $s['step']];
    }

    if ($id === 'servicos') {
        $lines = array_map(
            static fn(array $svc): string => "• {$svc['title']} — {$svc['price']}",
            $services
        );
        $messages[] = reply(
            "Serviços da unidade Vértice Fitness:\n\n" . implode("\n", $lines) . "\n\nAmbientes climatizados, toalhas premium e vestiários com amenities."
        );
        $messages[] = showMainMenu($s, 'Posso ajudar com mais alguma coisa?');
        return ['messages' => $messages, 'step' => $s['step']];
    }

    if ($id === 'horarios') {
        $messages[] = reply(
            "Horários Vértice Fitness Copacabana:\n\n• Seg a Sex — 05:30 às 23:00\n• Sábado — 07:00 às 20:00\n• Domingo e feriados — 08:00 às 14:00\n\nLounge VIP e spa seguem o mesmo expediente, com última entrada 45 min antes do fechamento."
        );
        $messages[] = showMainMenu($s, 'Quer agendar um horário ou ver planos?');
        return ['messages' => $messages, 'step' => $s['step']];
    }

    if ($id === 'agendar') {
        $s['step'] = 'schedule';
        $messages[] = reply('Perfeito. Qual horário prefere para o treino?', [
            'options' => scheduleOptions(),
        ]);
        return ['messages' => $messages, 'step' => $s['step']];
    }

    if ($id === 'cadastro') {
        $s['step'] = 'register';
        $s['draftStudent'] = ['name' => $s['userName'], 'phone' => ''];
        $text = $s['draftStudent']['name'] !== ''
            ? "Vamos cadastrar você, {$s['draftStudent']['name']}.\nConfirme ou digite o nome completo para a ficha:"
            : 'Vamos iniciar seu cadastro.\nDigite o nome completo do aluno:';
        $messages[] = reply($text);
        return ['messages' => $messages, 'step' => $s['step'], 'focus' => true];
    }

    if ($id === 'maps') {
        $messages[] = reply(
            "Como chegar na Vértice Fitness:\n\n" . UNIT_ADDRESS . "\n\nEstacionamento valet no térreo. Vou abrir o Google Maps para você."
        );
        $messages[] = showMainMenu($s, 'Mapa aberto. Precisa de mais alguma coisa?');
        return ['messages' => $messages, 'step' => $s['step'], 'openUrl' => MAPS_URL];
    }

    if ($id === 'atendente') {
        $name = $s['userName'] !== '' ? $s['userName'] : 'interessado(a)';
        $msg = rawurlencode("Olá, Vértice Fitness! Sou {$name} e gostaria de falar com um atendente.");
        $url = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . $msg;
        $messages[] = reply(
            "Claro. Vou te conectar com nossa equipe no WhatsApp agora.\nHorário de atendimento humano: 08:00 às 21:00."
        );
        $messages[] = showMainMenu($s, 'Atendente acionado. Posso ajudar com mais algo por aqui?');
        return ['messages' => $messages, 'step' => $s['step'], 'openUrl' => $url];
    }

    if ($id === 'menu') {
        $messages[] = showMainMenu($s);
        return ['messages' => $messages, 'step' => $s['step']];
    }

    foreach ($scheduleSlots as $slot) {
        if ($slot['id'] === $id) {
            $aluno = $s['userName'] !== '' ? $s['userName'] : 'convidado';
            $messages[] = reply(
                "Treino reservado com sucesso.\n\n{$slot['label']}\nAluno: {$aluno}\n\nVocê receberá a confirmação por WhatsApp. Chegue 10 min antes para o check-in biométrico."
            );
            $messages[] = showMainMenu($s, 'Agendamento concluído. O que mais deseja?');
            return ['messages' => $messages, 'step' => $s['step']];
        }
    }

    $messages[] = showMainMenu($s, 'Não entendi. Escolha uma opção abaixo:');
    return ['messages' => $messages, 'step' => $s['step']];
}

function handleText(string $text, array &$s): array
{
    $messages = [];

    if ($s['step'] === 'askName') {
        $parts = preg_split('/\s+/u', $text) ?: [];
        $first = $parts[0] ?? $text;
        $clean = preg_replace('/[^\p{L}\p{N}-]/u', '', $first) ?: mb_substr($text, 0, 24);
        $s['userName'] = mb_strtoupper(mb_substr($clean, 0, 1)) . mb_substr($clean, 1);
        $messages[] = reply(
            greetingName($s) . "! Bem-vindo(a) à Vértice Fitness.\nAqui você consulta planos, agenda treinos, cadastra-se e fala com nosso concierge."
        );
        $messages[] = showMainMenu($s);
        return ['messages' => $messages, 'step' => $s['step']];
    }

    if ($s['step'] === 'register') {
        $s['draftStudent']['name'] = $text;
        $s['step'] = 'registerPhone';
        $messages[] = reply(
            "Obrigado, {$s['draftStudent']['name']}.\nAgora digite um WhatsApp com DDD (somente números):"
        );
        return ['messages' => $messages, 'step' => $s['step']];
    }

    if ($s['step'] === 'registerPhone') {
        $digits = preg_replace('/\D+/', '', $text) ?? '';
        if (strlen($digits) < 10) {
            $messages[] = reply('Não reconheci o número. Envie com DDD, por exemplo: 21999998888');
            return ['messages' => $messages, 'step' => $s['step']];
        }
        $s['draftStudent']['phone'] = $digits;
        saveLead($s['draftStudent']);
        $phoneDisplay = str_starts_with($digits, '55') ? $digits : '55' . $digits;
        $messages[] = reply(
            "Cadastro registrado com sucesso.\n\nAluno: {$s['draftStudent']['name']}\nWhatsApp: +{$phoneDisplay}\nStatus: lista de espera / visita guiada\n\nNossa equipe entrará em contato em até 1 hora útil."
        );
        $messages[] = showMainMenu($s, 'Cadastro pronto. Deseja explorar planos ou agendar?');
        return ['messages' => $messages, 'step' => $s['step']];
    }

    if (in_array($s['step'], ['menu', 'schedule', 'idle'], true)) {
        $lower = mb_strtolower($text);
        if (preg_match('/plano|valor|preço|preco/u', $lower)) {
            return handleOption('planos', $s);
        }
        if (preg_match('/serviço|servico|personal|spa|musculação|musculacao|bike|cycling/u', $lower)) {
            return handleOption('servicos', $s);
        }
        if (preg_match('/agenda|treino|marcar/u', $lower)) {
            $s['step'] = 'schedule';
            $messages[] = reply('Claro. Escolha um horário:', ['options' => scheduleOptions()]);
            return ['messages' => $messages, 'step' => $s['step']];
        }
        if (preg_match('/hora|funcionamento|abre|fecha/u', $lower)) {
            return handleOption('horarios', $s);
        }
        if (preg_match('/mapa|chegar|endereço|endereco|local/u', $lower)) {
            return handleOption('maps', $s);
        }
        if (preg_match('/atendente|humano|falar|whatsapp/u', $lower)) {
            return handleOption('atendente', $s);
        }
        if (preg_match('/cadastro|matricula|aluno/u', $lower)) {
            $s['step'] = 'register';
            $s['draftStudent'] = ['name' => $s['userName'], 'phone' => ''];
            $textMsg = $s['draftStudent']['name'] !== ''
                ? "Vamos cadastrar você, {$s['draftStudent']['name']}. Digite o nome completo:"
                : 'Digite o nome completo do aluno:';
            $messages[] = reply($textMsg);
            return ['messages' => $messages, 'step' => $s['step']];
        }
        $messages[] = showMainMenu($s, 'Não entendi completamente. Use as opções abaixo para navegar com facilidade:');
        return ['messages' => $messages, 'step' => $s['step']];
    }

    $messages[] = showMainMenu($s);
    return ['messages' => $messages, 'step' => $s['step']];
}

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$action = (string) ($payload['action'] ?? '');
$s = state();

try {
    if ($action === 'boot') {
        $result = handleBoot();
    } elseif ($action === 'option') {
        $id = trim((string) ($payload['id'] ?? ''));
        if ($id === '') {
            throw new InvalidArgumentException('Opção inválida');
        }
        $result = handleOption($id, $s);
        saveState($s);
    } elseif ($action === 'message') {
        $text = trim((string) ($payload['text'] ?? ''));
        if ($text === '') {
            throw new InvalidArgumentException('Mensagem vazia');
        }
        $result = handleText($text, $s);
        saveState($s);
    } else {
        throw new InvalidArgumentException('Ação inválida');
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
