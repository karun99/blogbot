'use strict';

const INTERVAL_UNITS = ['minutes', 'hours', 'days', 'weeks'];
const TONES = ['professional', 'casual', 'technical', 'conversational', 'persuasive'];
const POST_STATUSES = ['draft', 'publish'];

function str(value, max) {
  return String(value == null ? '' : value).trim().slice(0, max || 255);
}

function sanitizeJobInput(input) {
  const unit = INTERVAL_UNITS.includes(input.interval_unit) ? input.interval_unit : 'hours';
  const status = POST_STATUSES.includes(input.post_status) ? input.post_status : 'draft';
  const tone = TONES.includes(input.tone) ? input.tone : 'professional';

  return {
    job_name: str(input.job_name, 255),
    brand_name: str(input.brand_name, 255),
    url: /^https?:\/\//.test(input.url) ? input.url : '',
    requirements: str(input.requirements, 20000),
    num_posts: Math.min(Math.max(Number(input.num_posts) || 1, 1), 50),
    interval_value: Math.max(Number(input.interval_value) || 1, 1),
    interval_unit: unit,
    post_status: status,
    word_count: Math.min(Math.max(Number(input.word_count) || 1000, 200), 5000),
    tone,
    seo_keywords: str(input.seo_keywords, 1000),
  };
}

function validateApiKey(key) {
  if (!key) {
    return false;
  }
  if (/^sk-or-v1-[a-zA-Z0-9_-]{50,}$/.test(key)) {
    return true;
  }
  return key.length >= 50;
}

module.exports = { sanitizeJobInput, validateApiKey, INTERVAL_UNITS, TONES, POST_STATUSES };
