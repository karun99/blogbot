'use strict';

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const config = require('./config');

const ALLOWED_TYPES = {
  pdf: 'application/pdf',
  docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  txt: 'text/plain',
  jpg: 'image/jpeg',
  png: 'image/png',
};

const MAX_SIZE = 5 * 1024 * 1024;

function extFromName(name) {
  const base = String(name || '').toLowerCase();
  const dot = base.lastIndexOf('.');
  return dot === -1 ? '' : base.slice(dot + 1);
}

function validateFile(file) {
  if (!file || !file.path) {
    return { ok: false, error: 'No file uploaded.' };
  }
  if (file.size > MAX_SIZE) {
    return { ok: false, error: 'File too large (max 5 MB).' };
  }
  const ext = extFromName(file.originalname);
  const mime = file.mimetype || '';
  if (!ALLOWED_TYPES[ext]) {
    return { ok: false, error: `Unsupported extension ".${ext}".` };
  }
  if (ext !== 'txt' && mime !== ALLOWED_TYPES[ext]) {
    return { ok: false, error: `MIME type ${mime} does not match extension .${ext}.` };
  }
  return { ok: true, ext, mime };
}

// Minimal ZIP reader that extracts a single file entry (used for DOCX).
function extractZipEntry(buf, targetName) {
  const eocd = buf.lastIndexOf(Buffer.from([0x50, 0x4b, 0x05, 0x06]));
  if (eocd === -1) {
    throw new Error('Not a ZIP file');
  }
  const count = buf.readUInt16LE(eocd + 10);
  const cdOffset = buf.readUInt32LE(eocd + 16);
  let cursor = cdOffset;
  for (let i = 0; i < count; i++) {
    if (buf.readUInt32LE(cursor) !== 0x02014b50) break;
    const nameLen = buf.readUInt16LE(cursor + 28);
    const extraLen = buf.readUInt16LE(cursor + 30);
    const compSize = buf.readUInt32LE(cursor + 20);
    const method = buf.readUInt16LE(cursor + 10);
    const localOffset = buf.readUInt32LE(cursor + 42);
    const name = buf.toString('utf8', cursor + 46, cursor + 46 + nameLen);
    if (name === targetName) {
      const lNameLen = buf.readUInt16LE(localOffset + 26);
      const lExtraLen = buf.readUInt16LE(localOffset + 28);
      const start = localOffset + 30 + lNameLen + lExtraLen;
      const raw = buf.subarray(start, start + compSize);
      if (method === 0) {
        return raw.toString('utf8');
      }
      return zlib.inflateRawSync(raw).toString('utf8');
    }
    cursor += 46 + nameLen + extraLen + buf.readUInt16LE(cursor + 32);
  }
  throw new Error(`Entry ${targetName} not found in ZIP`);
}

function parseDocx(buf) {
  const xml = extractZipEntry(buf, 'word/document.xml');
  return xml
    .replace(/<\/w:p>/gi, '\n')
    .replace(/<\/w:tab>/gi, '\t')
    .replace(/<[^>]+>/g, '')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&nbsp;/g, ' ')
    .replace(/\n\s*\n+/g, '\n')
    .trim();
}

function readAsBlob(filePath, mime) {
  return new Blob([fs.readFileSync(filePath)], { type: mime });
}

async function postForm(url, formData, timeoutMs) {
  const res = await fetch(url, { method: 'POST', body: formData, signal: AbortSignal.timeout(timeoutMs) });
  return res.json().catch(() => ({}));
}

async function ocrSpace(filePath, ext, mime) {
  const form = new FormData();
  form.append('apikey', config.ocrApiKey);
  form.append('language', 'eng');
  form.append('isOverlayRequired', 'false');
  form.append('file', readAsBlob(filePath, mime), `document.${ext}`);
  const body = await postForm(config.ocrEndpoint, form, 60000);
  if (!body.ParsedResults || !body.ParsedResults.length) {
    throw new Error(body.ErrorMessage || 'OCR returned no text.');
  }
  return body.ParsedResults.map((r) => r.ParsedText).join('\n').trim();
}

async function baidu(filePath) {
  const form = new FormData();
  form.append('apikey', config.ocrApiKey);
  form.append('image', fs.readFileSync(filePath).toString('base64'));
  form.append('detect_direction', 'true');
  const body = await postForm(config.ocrEndpoint, form, 60000);
  if (!body.words_result) {
    throw new Error(body.error_msg || 'Baidu OCR returned no text.');
  }
  return body.words_result.map((r) => r.words).join('\n').trim();
}

async function selfHosted(filePath, ext, mime) {
  const form = new FormData();
  form.append('file', readAsBlob(filePath, mime), `document.${ext}`);
  const body = await postForm(config.ocrEndpoint, form, 60000);
  if (body && typeof body.text === 'string') {
    return body.text.trim();
  }
  if (typeof body === 'string' && body.trim()) {
    return body.trim();
  }
  throw new Error('Self-hosted OCR returned no text.');
}

async function extractText(file) {
  const check = validateFile(file);
  if (!check.ok) {
    throw new Error(check.error);
  }

  const filePath = file.path;
  const buf = fs.readFileSync(filePath);

  if (check.ext === 'txt') {
    return buf.toString('utf8');
  }

  if (check.ext === 'docx') {
    return parseDocx(buf);
  }

  const endpoint = config.ocrEndpoint;
  if (endpoint.includes('aip.baidubce.com')) {
    return baidu(filePath);
  }
  if (endpoint.includes('localhost') || endpoint.includes('127.0.0.1')) {
    return selfHosted(filePath, check.ext, check.mime);
  }
  return ocrSpace(filePath, check.ext, check.mime);
}

function cleanupUpload(file) {
  if (file && file.path) {
    try {
      fs.unlinkSync(file.path);
    } catch (err) {
      /* ignore */
    }
  }
}

module.exports = { extractText, cleanupUpload, ALLOWED_TYPES, MAX_SIZE };
