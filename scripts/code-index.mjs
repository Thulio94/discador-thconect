import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, realpathSync, statSync, writeFileSync } from 'node:fs';
import { extname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const INDEX_VERSION = 1;
const VECTOR_DIMENSIONS = 1536;
const CHUNK_LINES = 72;
const CHUNK_OVERLAP = 12;
const MAX_FILE_BYTES = 512 * 1024;
const DEFAULT_INDEX_PATH = '.code-index/code-index.json';

const ALLOWED_EXTENSIONS = new Set([
    '.php', '.js', '.mjs', '.cjs', '.ts', '.tsx', '.vue', '.css', '.scss',
    '.go', '.md', '.json', '.yml', '.yaml', '.xml', '.sql', '.sh', '.ps1',
]);

const ALLOWED_FILENAMES = new Set(['dockerfile', 'makefile']);
const BLOCKED_SEGMENTS = new Set([
    '.git', '.code-index', 'node_modules', 'vendor', 'storage', 'public/build',
    'public/storage', 'test-results', 'recordings', 'backups',
]);
const BLOCKED_FILENAMES = new Set([
    '.env', '.env.backup', '.env.production', 'auth.json', 'composer.lock',
    'package-lock.json', 'skills-lock.json',
]);

const STOP_WORDS = new Set([
    'a', 'ao', 'aos', 'as', 'com', 'como', 'da', 'das', 'de', 'do', 'dos', 'e',
    'em', 'esse', 'esta', 'este', 'isso', 'na', 'nas', 'no', 'nos', 'o', 'os',
    'ou', 'para', 'por', 'que', 'se', 'sem', 'um', 'uma', 'the', 'to', 'of',
    'and', 'or', 'in', 'on', 'for', 'from', 'is', 'are', 'this', 'that', 'with',
]);

const DOMAIN_ALIASES = [
    ['agente', 'agent', 'operador', 'operator'],
    ['ligacao', 'ligacoes', 'chamada', 'chamadas', 'call', 'calls'],
    ['campanha', 'campanhas', 'campaign', 'campaigns'],
    ['equipe', 'equipes', 'team', 'teams'],
    ['usuario', 'usuarios', 'user', 'users'],
    ['supervisao', 'supervisor', 'supervision', 'monitoramento'],
    ['relatorio', 'relatorios', 'report', 'reports'],
    ['ramal', 'ramais', 'extension', 'extensions'],
    ['tabulacao', 'tabulacoes', 'disposition', 'dispositions'],
    ['gravacao', 'gravacoes', 'recording', 'recordings'],
    ['lista', 'listas', 'mailing', 'contactlist'],
    ['retorno', 'retornos', 'agendamento', 'agendamentos', 'callback', 'callbacks'],
    ['empresa', 'empresas', 'tenant', 'tenants', 'company', 'companies'],
    ['permissao', 'permissoes', 'permission', 'permissions', 'rbac'],
    ['rota', 'rotas', 'route', 'routes', 'trunk'],
    ['presenca', 'presence', 'status', 'estado'],
    ['painel', 'dashboard', 'console'],
    ['tela', 'pagina', 'view', 'blade', 'page', 'interface'],
    ['fila', 'filas', 'queue', 'queues', 'job', 'jobs'],
    ['importacao', 'importacoes', 'import', 'imports'],
    ['exportacao', 'exportacoes', 'export', 'exports'],
    ['autenticacao', 'login', 'auth', 'authentication'],
    ['telefonia', 'telephony', 'asterisk', 'pjsip', 'sip'],
    ['limite', 'limites', 'teto', 'capacidade', 'capacity', 'limit', 'limits', 'quota', 'cap'],
    ['seguranca', 'security', 'vulnerabilidade', 'vulnerability', 'idor'],
    ['temporeal', 'realtime', 'websocket', 'reverb', 'broadcast'],
    ['perfil', 'perfis', 'profile', 'profiles', 'role', 'roles'],
];

const ALIAS_LOOKUP = new Map();
for (const group of DOMAIN_ALIASES) {
    for (const word of group) ALIAS_LOOKUP.set(word, group);
}

function normalizeText(value) {
    return String(value)
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

export function tokenize(value, { expandAliases = true } = {}) {
    const base = normalizeText(value)
        .split(/[^a-z0-9]+/)
        .filter((token) => token.length > 1 && !STOP_WORDS.has(token));
    const tokens = [...base];

    for (let index = 0; index < base.length - 1; index += 1) {
        tokens.push(`${base[index]}_${base[index + 1]}`);
    }

    if (expandAliases) {
        for (const token of base) {
            const aliases = ALIAS_LOOKUP.get(token);
            if (aliases) tokens.push(...aliases);
        }
    }

    return tokens;
}

export function hashToken(token) {
    let hash = 2166136261;
    for (let index = 0; index < token.length; index += 1) {
        hash ^= token.charCodeAt(index);
        hash = Math.imul(hash, 16777619);
    }
    return hash >>> 0;
}

function termFrequencies(text) {
    const frequencies = new Map();
    for (const token of tokenize(text)) {
        const dimension = hashToken(token) % VECTOR_DIMENSIONS;
        frequencies.set(dimension, (frequencies.get(dimension) ?? 0) + 1);
    }
    return frequencies;
}

function vectorize(frequencies, documentFrequencies, documentCount) {
    const vector = [];
    let norm = 0;
    for (const [dimension, count] of frequencies) {
        const idf = Math.log((documentCount + 1) / ((documentFrequencies[dimension] ?? 0) + 1)) + 1;
        const weight = (1 + Math.log(count)) * idf;
        vector.push([dimension, weight]);
        norm += weight * weight;
    }

    const divisor = Math.sqrt(norm) || 1;
    return vector.sort((a, b) => a[0] - b[0]).map(([dimension, weight]) => [dimension, weight / divisor]);
}

export function isSafeSourceFile(path) {
    const normalized = path.replaceAll('\\', '/');
    const lower = normalized.toLowerCase();
    const parts = lower.split('/');
    if (parts.some((_, index) => BLOCKED_SEGMENTS.has(parts.slice(0, index + 1).join('/')))) return false;
    if (parts.some((part) => BLOCKED_SEGMENTS.has(part))) return false;
    if (BLOCKED_FILENAMES.has(parts.at(-1))) return false;
    if (lower.startsWith('.') && !lower.startsWith('.docker/')) return false;
    return ALLOWED_EXTENSIONS.has(extname(lower)) || lower.endsWith('.blade.php') || ALLOWED_FILENAMES.has(parts.at(-1));
}

function repositoryFiles(root) {
    const output = execFileSync('git', ['ls-files', '--cached', '--others', '--exclude-standard', '-z'], {
        cwd: root,
        encoding: 'utf8',
        maxBuffer: 20 * 1024 * 1024,
    });
    return output.split('\0').filter(Boolean).filter(isSafeSourceFile).sort();
}

function titleForChunk(path, lines) {
    const sample = lines.slice(0, 24).join('\n');
    const patterns = [
        /\b(?:class|interface|trait|enum)\s+([A-Za-z_$][\w$]*)/,
        /\bfunction\s+([A-Za-z_$][\w$]*)\s*\(/,
        /\b(?:const|let|var)\s+([A-Za-z_$][\w$]*)\s*=\s*(?:async\s*)?\(/,
        /Route::(?:get|post|put|patch|delete|resource|apiResource)\s*\(\s*['"]([^'"]+)/,
        /^#{1,4}\s+(.+)$/m,
    ];
    for (const pattern of patterns) {
        const match = sample.match(pattern);
        if (match) return match[1].trim();
    }
    return path.split('/').at(-1);
}

function chunkFile(root, path) {
    const absolutePath = join(root, ...path.split('/'));
    const realRoot = `${realpathSync(root).toLowerCase()}${sep}`;
    const realPath = realpathSync(absolutePath).toLowerCase();
    if (!realPath.startsWith(realRoot)) return [];
    const stats = statSync(absolutePath);
    if (stats.size === 0 || stats.size > MAX_FILE_BYTES) return [];
    const content = readFileSync(absolutePath, 'utf8').replaceAll('\r\n', '\n');
    if (content.includes('\0')) return [];
    const lines = content.split('\n');
    const chunks = [];

    for (let start = 0; start < lines.length; start += CHUNK_LINES - CHUNK_OVERLAP) {
        const selected = lines.slice(start, start + CHUNK_LINES);
        const body = selected.join('\n').trim();
        if (!body) continue;
        const title = titleForChunk(path, selected);
        const end = Math.min(start + selected.length, lines.length);
        chunks.push({
            id: createHash('sha1').update(`${path}:${start + 1}:${body}`).digest('hex').slice(0, 16),
            path,
            startLine: start + 1,
            endLine: end,
            title,
            preview: selected.find((line) => line.trim())?.trim().slice(0, 240) ?? title,
            searchableText: `${path.replaceAll('/', ' ')} ${title} ${body}`,
        });
        if (end === lines.length) break;
    }
    return chunks;
}

export function buildIndex(root, outputPath = DEFAULT_INDEX_PATH) {
    const repositoryRoot = resolve(root);
    const chunks = repositoryFiles(repositoryRoot).flatMap((path) => chunkFile(repositoryRoot, path));
    const frequencies = chunks.map((chunk) => termFrequencies(chunk.searchableText));
    const documentFrequencies = Array(VECTOR_DIMENSIONS).fill(0);
    for (const frequency of frequencies) {
        for (const dimension of frequency.keys()) documentFrequencies[dimension] += 1;
    }

    const documents = chunks.map((chunk, index) => ({
        id: chunk.id,
        path: chunk.path,
        startLine: chunk.startLine,
        endLine: chunk.endLine,
        title: chunk.title,
        preview: chunk.preview,
        vector: vectorize(frequencies[index], documentFrequencies, chunks.length),
    }));
    const index = {
        version: INDEX_VERSION,
        algorithm: 'local-feature-hashing-tfidf',
        dimensions: VECTOR_DIMENSIONS,
        createdAt: new Date().toISOString(),
        root: repositoryRoot,
        fileCount: new Set(documents.map((document) => document.path)).size,
        chunkCount: documents.length,
        documentFrequencies,
        documents,
    };
    const absoluteOutput = resolve(repositoryRoot, outputPath);
    mkdirSync(resolve(absoluteOutput, '..'), { recursive: true });
    writeFileSync(absoluteOutput, JSON.stringify(index));
    return { index, outputPath: absoluteOutput };
}

function dotProduct(left, right) {
    let leftIndex = 0;
    let rightIndex = 0;
    let score = 0;
    while (leftIndex < left.length && rightIndex < right.length) {
        if (left[leftIndex][0] === right[rightIndex][0]) {
            score += left[leftIndex][1] * right[rightIndex][1];
            leftIndex += 1;
            rightIndex += 1;
        } else if (left[leftIndex][0] < right[rightIndex][0]) {
            leftIndex += 1;
        } else {
            rightIndex += 1;
        }
    }
    return score;
}

export function searchIndex(index, query, limit = 12) {
    const queryVector = vectorize(termFrequencies(query), index.documentFrequencies, index.chunkCount);
    const queryTokens = new Set(tokenize(query));
    return index.documents
        .map((document) => {
            const pathTokens = new Set(tokenize(`${document.path} ${document.title}`));
            const pathMatches = [...queryTokens].filter((token) => pathTokens.has(token)).length;
            const sourceBoost = /^(app|resources|routes|services)\//.test(document.path)
                ? 0.06
                : /^(config|database|tests)\//.test(document.path) ? 0.025 : 0;
            return {
                ...document,
                score: dotProduct(queryVector, document.vector) + Math.min(pathMatches, 6) * 0.025 + sourceBoost,
            };
        })
        .filter((document) => document.score > 0)
        .sort((a, b) => b.score - a.score || a.path.localeCompare(b.path) || a.startLine - b.startLine)
        .slice(0, limit);
}

function loadIndex(root, indexPath = DEFAULT_INDEX_PATH) {
    const absolutePath = resolve(root, indexPath);
    if (!existsSync(absolutePath)) throw new Error(`Indice ausente. Execute: npm run code:index`);
    const index = JSON.parse(readFileSync(absolutePath, 'utf8'));
    if (index.version !== INDEX_VERSION) throw new Error('Indice desatualizado. Reconstrua com npm run code:index.');
    return { index, absolutePath };
}

function printResults(results) {
    if (results.length === 0) {
        console.log('Nenhum resultado encontrado.');
        return;
    }
    for (const [position, result] of results.entries()) {
        console.log(`${position + 1}. ${result.path}:${result.startLine}  score=${result.score.toFixed(4)}  ${result.title}`);
        console.log(`   ${result.preview}`);
    }
}

function runCli() {
    const [command = 'help', ...args] = process.argv.slice(2);
    const root = process.cwd();
    if (command === 'build') {
        const { index, outputPath } = buildIndex(root);
        console.log(`Indice criado: ${relative(root, outputPath)}`);
        console.log(`${index.fileCount} arquivos, ${index.chunkCount} trechos, ${index.dimensions} dimensoes.`);
        return;
    }
    if (command === 'search') {
        const query = args.join(' ').trim();
        if (!query) throw new Error('Informe uma busca. Ex.: npm run code:search -- "status do agente na supervisao"');
        const { index } = loadIndex(root);
        printResults(searchIndex(index, query));
        return;
    }
    if (command === 'stats') {
        const { index, absolutePath } = loadIndex(root);
        console.log(JSON.stringify({
            path: absolutePath,
            createdAt: index.createdAt,
            algorithm: index.algorithm,
            dimensions: index.dimensions,
            files: index.fileCount,
            chunks: index.chunkCount,
        }, null, 2));
        return;
    }
    console.log('Uso: node scripts/code-index.mjs <build|search|stats> [consulta]');
}

const invokedPath = process.argv[1] ? resolve(process.argv[1]) : '';
if (invokedPath === fileURLToPath(import.meta.url)) {
    try {
        runCli();
    } catch (error) {
        console.error(`Erro: ${error.message}`);
        process.exitCode = 1;
    }
}
