'use strict';

const path = require('path');
const fs = require('fs');

const ROOT = path.join(__dirname, '..');

function fromEnv(name, fallback) {
  const value = process.env[name];
  return value === undefined || value === '' ? fallback : value;
}

function ensureDir(dir) {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
  return dir;
}

const config = {
  port: parseInt(fromEnv('PORT', '8080'), 10),
  nodeEnv: fromEnv('NODE_ENV', 'development'),
  openrouterApiKey: fromEnv('OPENROUTER_API_KEY', ''),
  openrouterModel: fromEnv('OPENROUTER_MODEL', 'meta-llama/llama-3.1-8b-instruct:free'),
  ocrEndpoint: fromEnv('OCR_ENDPOINT', 'https://api.ocr.space/parse/image'),
  ocrApiKey: fromEnv('OCR_API_KEY', ''),
  encryptionKey: fromEnv('BLOGWRITER_ENCRYPTION_KEY', 'blogwriter-default-key-please-rotate'),
  rateLimitRequests: parseInt(fromEnv('RATE_LIMIT_REQUESTS', '10'), 10),
  rateLimitWindow: parseInt(fromEnv('RATE_LIMIT_WINDOW', '60'), 10),
  adminToken: fromEnv('ADMIN_TOKEN', ''),
  schedulerTickMs: parseInt(fromEnv('SCHEDULER_TICK_MS', '60000'), 10),
  dataDir: ensureDir(fromEnv('DATA_DIR', path.join(ROOT, 'data'))),
  postsDir: ensureDir(path.join(fromEnv('DATA_DIR', path.join(ROOT, 'data')), 'posts')),
  publicDir: path.join(ROOT, 'public'),
  jobsFile: path.join(fromEnv('DATA_DIR', path.join(ROOT, 'data')), 'jobs.json'),
  uploadsDir: ensureDir(path.join(fromEnv('DATA_DIR', path.join(ROOT, 'data')), 'uploads')),
};

module.exports = config;
