'use strict';

const config = require('./config');
const encryption = require('./encryption');

const OPENROUTER_URL = 'https://openrouter.ai/api/v1/chat/completions';
const MODELS_URL = 'https://openrouter.ai/api/v1/models';

const FALLBACK_MODELS = [
  'meta-llama/llama-3.1-8b-instruct:free',
  'meta-llama/llama-3.2-3b-instruct:free',
  'mistralai/mistral-7b-instruct:free',
  'google/gemma-2-9b-it:free',
  'openai/gpt-4o-mini:free',
  'deepseek/deepseek-chat:free',
  'qwen/qwen-2.5-7b-instruct:free',
  'microsoft/phi-3-mini-128k-instruct:free',
  'anthropic/claude-3-5-haiku:free',
];

// In-memory settings store (override env at runtime via /api/settings).
let apiKey = config.openrouterApiKey;
let apiKeyEncrypted = '';
let preferredModel = config.openrouterModel;
let rateLimitRequests = config.rateLimitRequests;
let rateLimitWindow = config.rateLimitWindow;

const callLog = [];

function getApiKey() {
  if (apiKey) {
    return apiKey;
  }
  return encryption.decrypt(apiKeyEncrypted);
}

function setApiKey(plain) {
  apiKeyEncrypted = encryption.encrypt(plain);
  apiKey = plain;
  return true;
}

function hasApiKey() {
  return Boolean(getApiKey());
}

function checkRateLimit() {
  const now = Date.now();
  const windowStart = now - rateLimitWindow * 1000;
  while (callLog.length && callLog[0] < windowStart) {
    callLog.shift();
  }
  if (callLog.length >= rateLimitRequests) {
    return false;
  }
  callLog.push(now);
  return true;
}

function activeModels() {
  const list = preferredModel ? [preferredModel] : [];
  return Array.from(new Set(list.concat(FALLBACK_MODELS)));
}

function extractUrlContent(url) {
  return fetch(url, {
    headers: { 'user-agent': 'BlogWriter/1.0' },
    signal: AbortSignal.timeout(20000),
  })
    .then((res) => (res.ok ? res.text() : Promise.reject(new Error('fetch failed'))))
    .then((html) => {
      const text = String(html)
        .replace(/<script[\s\S]*?<\/script>/gi, ' ')
        .replace(/<style[\s\S]*?<\/style>/gi, ' ')
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
      return text.slice(0, 8000);
    })
    .catch(() => '');
}

function buildPrompt(job) {
  const parts = [
    'You are BlogWriter, an expert SEO blog writer.',
    'Produce one complete, ready-to-publish blog post in clean HTML (h2/h3, <p>, <ul>).',
  ];
  if (job.brand_name) parts.push(`Brand / Company: ${job.brand_name}`);
  if (job.tone) parts.push(`Tone: ${job.tone}`);
  if (job.word_count) parts.push(`Target length: approximately ${job.word_count} words.`);
  if (job.seo_keywords) parts.push(`SEO keywords to include naturally: ${job.seo_keywords}`);

  const prompt = async () => {
    if (job.url) {
      const ref = await extractUrlContent(job.url);
      if (ref) parts.push(`Reference content from this URL (use as inspiration, do not copy):\n${ref}`);
    }
    if (job.requirements) parts.push(`Content requirements: ${job.requirements}`);
    return parts.join('\n\n');
  };

  return prompt();
}

async function requestCompletion(key, model, prompt) {
  const res = await fetch(OPENROUTER_URL, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${key}`,
      'Content-Type': 'application/json',
      'X-Title': 'BlogWriter',
    },
    signal: AbortSignal.timeout(300000),
    body: JSON.stringify({
      model,
      messages: [{ role: 'user', content: prompt }],
      temperature: 0.7,
      max_tokens: 4096,
    }),
  });

  const body = await res.json().catch(() => ({}));

  if (!res.ok) {
    const reason = body.error && body.error.message ? body.error.message : `HTTP ${res.status}`;
    throw new Error(reason);
  }

  const content = body.choices && body.choices[0] && body.choices[0].message.content;
  if (!content) {
    throw new Error('Empty model response');
  }
  return content;
}

async function generatePost(job) {
  if (!checkRateLimit()) {
    throw new Error('API rate limit exceeded. Try again later.');
  }

  const key = getApiKey();
  if (!key) {
    throw new Error('OpenRouter API key is not configured.');
  }

  const prompt = await buildPrompt(job);
  const errors = [];

  for (const model of activeModels()) {
    try {
      const content = await requestCompletion(key, model, prompt);
      return { content, model };
    } catch (err) {
      errors.push(`${model}: ${err.message}`);
    }
  }

  throw new Error(`All AI models failed. ${errors.join(' | ')}`);
}

async function validateRemote() {
  const key = getApiKey();
  if (!key) {
    return false;
  }
  const res = await fetch(MODELS_URL, {
    headers: { Authorization: `Bearer ${key}` },
    signal: AbortSignal.timeout(20000),
  });
  return res.ok;
}

function setRuntimeSettings({ apiKey: key, model }) {
  if (key) {
    setApiKey(key);
  }
  if (model) {
    preferredModel = model;
  }
  return { hasKey: hasApiKey(), model: preferredModel };
}

function getRuntimeSettings() {
  return {
    hasApiKey: hasApiKey(),
    model: preferredModel,
    rateLimitRequests,
    rateLimitWindow,
  };
}

module.exports = {
  FALLBACK_MODELS,
  generatePost,
  validateRemote,
  hasApiKey,
  setApiKey,
  getApiKey,
  setRuntimeSettings,
  getRuntimeSettings,
};
