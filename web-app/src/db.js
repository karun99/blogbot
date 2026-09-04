'use strict';

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const config = require('./config');

const dbFile = config.jobsFile;

function normalizeJob(job) {
  const now = new Date().toISOString();
  return {
    id: job.id || null,
    job_name: job.job_name || '',
    brand_name: job.brand_name || '',
    url: job.url || '',
    requirements: job.requirements || '',
    num_posts: Number(job.num_posts) || 1,
    interval_value: Number(job.interval_value) || 24,
    interval_unit: ['minutes', 'hours', 'days', 'weeks'].includes(job.interval_unit) ? job.interval_unit : 'hours',
    post_status: job.post_status === 'publish' ? 'publish' : 'draft',
    word_count: Number(job.word_count) || 1000,
    tone: job.tone || 'professional',
    seo_keywords: job.seo_keywords || '',
    status: job.status || 'paused',
    total_posts: Number(job.total_posts) || 0,
    generated_posts: Number(job.generated_posts) || 0,
    last_run: job.last_run || null,
    next_run: job.next_run || null,
    created_at: job.created_at || now,
    updated_at: job.updated_at || now,
  };
}

function readJobs() {
  try {
    const raw = fs.readFileSync(dbFile, 'utf8');
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch (err) {
    if (err.code === 'ENOENT') {
      return [];
    }
    throw err;
  }
}

function writeJobs(jobs) {
  fs.writeFileSync(dbFile, JSON.stringify(jobs, null, 2), 'utf8');
}

function nextId(jobs) {
  return jobs.reduce((max, j) => Math.max(max, Number(j.id) || 0), 0) + 1;
}

function createJob(input) {
  const jobs = readJobs();
  const job = normalizeJob({ ...input, id: nextId(jobs) });
  jobs.push(job);
  writeJobs(jobs);
  return job;
}

function getJob(id) {
  const jobs = readJobs();
  return jobs.find((j) => Number(j.id) === Number(id)) || null;
}

function listJobs() {
  return readJobs().slice().reverse();
}

function updateJob(id, patch) {
  const jobs = readJobs();
  const idx = jobs.findIndex((j) => Number(j.id) === Number(id));
  if (idx === -1) {
    return null;
  }
  jobs[idx] = normalizeJob({ ...jobs[idx], ...patch, updated_at: new Date().toISOString() });
  writeJobs(jobs);
  return jobs[idx];
}

function deleteJob(id) {
  const jobs = readJobs();
  const next = jobs.filter((j) => Number(j.id) !== Number(id));
  const removed = next.length !== jobs.length;
  if (removed) {
    writeJobs(next);
  }
  return removed;
}

function getDueJobs() {
  const now = new Date();
  return readJobs().filter((j) => {
    if (j.status !== 'running') {
      return false;
    }
    if (!j.next_run) {
      return true;
    }
    return new Date(j.next_run) <= now;
  });
}

function savePost(jobId, post) {
  const dir = path.join(config.postsDir, String(jobId));
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
  const file = path.join(dir, `${Date.now()}-${post.id}.json`);
  fs.writeFileSync(file, JSON.stringify(post, null, 2), 'utf8');
  return file;
}

function listPosts(jobId) {
  const dir = path.join(config.postsDir, String(jobId));
  if (!fs.existsSync(dir)) {
    return [];
  }
  return fs
    .readdirSync(dir)
    .filter((f) => f.endsWith('.json'))
    .map((f) => {
      try {
        return JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'));
      } catch (err) {
        return null;
      }
    })
    .filter(Boolean);
}

module.exports = {
  createJob,
  getJob,
  listJobs,
  updateJob,
  deleteJob,
  getDueJobs,
  savePost,
  listPosts,
};
