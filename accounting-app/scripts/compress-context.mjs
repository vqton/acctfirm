#!/usr/bin/env node
// Nén context cho agent workflow — giảm token, giữ nguyên nghiệp vụ
// Usage: node scripts/compress-context.mjs [files...]
//   --output  Đường dẫn file output (mặc định: docs/context-compressed.md)
//   --budget  Token budget (mặc định: 4000)
//   --dry-run Chỉ xem thống kê, không nén
//
// Yêu cầu: HEADROOM_BASE_URL hoặc HEADROOM_API_KEY trong .env
// Fallback: Nếu không có headroom-ai hoặc proxy, chạy dry-run

import { readFileSync, writeFileSync, existsSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';
import { createRequire } from 'module';

const __dirname = dirname(fileURLToPath(import.meta.url));
const require = createRequire(import.meta.url);

const ROOT = join(__dirname, '..', '..'); // ~/BookWise
const DEFAULT_FILES = [
    join(ROOT, 'AGENTS.md'),
    join(ROOT, 'accounting-app/docs/analysis/bc01-tt99-full-analysis.md'),
    join(ROOT, 'accounting-app/docs/analysis/bc02-tt99-full-analysis.md'),
    join(ROOT, 'accounting-app/docs/analysis/bc03-tt99-full-analysis.md'),
];
const OUTPUT_DEFAULT = join(ROOT, 'accounting-app/docs/context-compressed.md');

function parseArgs() {
    const args = process.argv.slice(2);
    const files = [];
    let output = OUTPUT_DEFAULT;
    let budget = 4000;
    let dryRun = false;
    for (let i = 0; i < args.length; i++) {
        if (args[i] === '--output' && args[i + 1]) { output = args[i + 1]; i++; }
        else if (args[i] === '--budget' && args[i + 1]) { budget = parseInt(args[i + 1]); i++; }
        else if (args[i] === '--dry-run') { dryRun = true; }
        else if (!args[i].startsWith('--')) { files.push(args[i]); }
    }
    return { files: files.length ? files : DEFAULT_FILES, output, budget, dryRun };
}

function readContext(files) {
    const sections = [];
    for (const f of files) {
        const absPath = existsSync(f) ? f : join(__dirname, f);
        if (!existsSync(absPath)) { console.warn(`[WARN] File not found: ${f}`); continue; }
        const content = readFileSync(absPath, 'utf-8');
        const name = f.replace(/^.*[/\\]/, '').replace('.md', '');
        sections.push({ name, content, tokens: Math.ceil(content.length / 4) });
    }
    return sections;
}

async function main() {
    const { files, output, budget, dryRun } = parseArgs();
    const sections = readContext(files);

    // Token estimation (4 chars ≈ 1 token)
    const totalTokens = sections.reduce((s, c) => s + c.tokens, 0);
    console.log(`\n=== Context Compression (${files.length} files) ===`);
    for (const s of sections) {
        console.log(`  ${s.name}: ${s.tokens.toLocaleString()} tokens (${(s.content.length / 1024).toFixed(1)} KB)`);
    }
    console.log(`  ─────────────────────────────`);
    console.log(`  Total: ${totalTokens.toLocaleString()} tokens`);

    if (dryRun) {
        console.log(`\n[Dry-run] Would compress to ~${budget} tokens (saving ~${(totalTokens - budget).toLocaleString()} tokens)`);
        console.log(`[Dry-run] Output: ${output}\n`);
        return;
    }

    // Thử dùng headroom-ai nếu có proxy
    let headroom = null;
    try {
        headroom = require('headroom-ai');
    } catch { }

    const baseUrl = process.env.HEADROOM_BASE_URL || 'http://localhost:8787';
    const apiKey = process.env.HEADROOM_API_KEY;

    if (!headroom || !apiKey) {
        console.log(`\n⚠  headroom-ai proxy không khả dụng.`);
        console.log(`   Set HEADROOM_BASE_URL và HEADROOM_API_KEY trong .env`);
        console.log(`   Hoặc chạy: headroom proxy`);
        console.log(`\n   Đang fallback: ghi context gốc + metadata để dùng offline.\n`);
        // Viết file context kèm metadata để script agent đọc
        const md = sections.map(s =>
            `## ${s.name}\n*tokens: ${s.tokens} | chars: ${s.content.length}*\n\n${s.content.slice(0, 5000)}...`
        ).join('\n\n---\n\n');
        writeFileSync(output, `# Compressed Context (fallback — uncompressed)\n\n> Generated: ${new Date().toISOString()}\n> Files: ${files.join(', ')}\n> Budget: ${budget}\n> Total tokens: ${totalTokens}\n\n${md}`);
        console.log(`Written to ${output} (${(readFileSync(output).length / 1024).toFixed(1)} KB)`);
        return;
    }

    // Compress with headroom-ai
    console.log(`\n🚀 Compressing via ${baseUrl}...`);
    const combined = sections.map(s => `[${s.name}]\n${s.content}`).join('\n\n');
    const messages = [{ role: 'user', content: combined }];

    try {
        const result = await headroom.compress(messages, {
            model: 'gpt-4o',
            baseUrl,
            apiKey,
            tokenBudget: budget,
            fallback: true,
        });
        const ratio = ((1 - result.compressionRatio) * 100).toFixed(0);
        writeFileSync(output, `# Compressed Context\n\n> Generated: ${new Date().toISOString()}\n> Files: ${files.join(', ')}\n> Budget: ${budget}\n> Original: ${totalTokens} tokens\n> Compressed: ${result.tokensSaved} saved (${ratio}%)\n\n${result.messages[0].content}\n`);
        console.log(`✅ Saved ${result.tokensSaved} tokens (${ratio}%) → ${output}`);
    } catch (err) {
        console.error(`❌ Compression failed: ${err.message}`);
        process.exit(1);
    }
}

main();
