'use strict';

const express = require('express');
const multer = require('multer');
const db = require('./db');
const ai = require('./ai');
const ocr = require('./ocr');
const validator = require('./validator');
const scheduler = require('./scheduler');
const config = require('./config');

const router = express.Router();

const upload = multer({
  dest: config.uploadsDir,
  limits: { fileSize: ocr.MAX_SIZE },
});

function requireAuth(req, res, next) {
  if (!config.adminToken) {
    return next();
  }
  const token = req.get('x-admin-token');
  if (!token || token !== config.adminToken) {
    return res.status(401).json({ success: false, message: 'Unauthorized' });
  }
  return next();
}

router.use(requireAuth);

router.get('/status', (req, res) => {
  res.json({
    success: true,
    app: 'blogwriter-web',
    version: '1.0.0',
    api: { ...ai.getRuntimeSettings(), models: ai.FALLBACK_MODELS },
    scheduler: { tickMs: config.schedulerTickMs },
    time: new Date().toISOString(),
  });
});

router.get('/settings', (req, res) => {
  res.json({ success: true, settings: ai.getRuntimeSettings() });
});

router.post('/settings', (req, res) => {
  const { apiKey, model } = req.body || {};
  if (apiKey && !validator.validateApiKey(apiKey)) {
    return res.status(400).json({ success: false, message: 'Invalid API key format.' });
  }
  const settings = ai.setRuntimeSettings({ apiKey, model });
  res.json({ success: true, settings });
});

router.get('/jobs', (req, res) => {
  res.json({ success: true, jobs: db.listJobs() });
});

router.post('/jobs', (req, res) => {
  const sanitized = validator.sanitizeJobInput(req.body || {});
  if (!sanitized.job_name) {
    return res.status(400).json({ success: false, message: 'Job name is required.' });
  }
  const job = db.createJob(sanitized);
  res.status(201).json({
    success: true,
    job,
    message: 'Job created successfully.',
    next_run: scheduler.nextRunAt(job),
  });
});

router.get('/jobs/:id', (req, res) => {
  const job = db.getJob(req.params.id);
  if (!job) {
    return res.status(404).json({ success: false, message: 'Job not found.' });
  }
  res.json({ success: true, job, posts: db.listPosts(job.id) });
});

router.post('/jobs/:id/run', async (req, res) => {
  const job = db.getJob(req.params.id);
  if (!job) {
    return res.status(404).json({ success: false, message: 'Job not found.' });
  }
  try {
    const created = await scheduler.runJob(job);
    const updated = db.getJob(job.id);
    res.json({ success: true, message: `Generated ${created.length} post(s).`, job: updated, posts: created });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

router.post('/jobs/:id/pause', (req, res) => {
  const job = db.updateJob(req.params.id, { status: 'paused' });
  if (!job) {
    return res.status(404).json({ success: false, message: 'Job not found.' });
  }
  res.json({ success: true, status: 'paused', job });
});

router.post('/jobs/:id/resume', (req, res) => {
  const current = db.getJob(req.params.id);
  if (!current) {
    return res.status(404).json({ success: false, message: 'Job not found.' });
  }
  const job = db.updateJob(req.params.id, {
    status: 'running',
    next_run: scheduler.nextRunAt(current),
  });
  res.json({ success: true, status: 'running', job });
});

router.delete('/jobs/:id', (req, res) => {
  const removed = db.deleteJob(req.params.id);
  if (!removed) {
    return res.status(404).json({ success: false, message: 'Job not found.' });
  }
  res.json({ success: true, message: 'Job deleted.' });
});

router.post('/ocr', upload.single('document'), async (req, res) => {
  const file = req.file;
  try {
    const text = await ocr.extractText(file);
    res.json({ success: true, text, chars: text.length });
  } catch (err) {
    res.status(400).json({ success: false, message: err.message });
  } finally {
    ocr.cleanupUpload(file);
  }
});

module.exports = router;
