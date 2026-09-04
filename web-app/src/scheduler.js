'use strict';

const db = require('./db');
const ai = require('./ai');

let timer = null;
let running = false;

function intervalToMs(value, unit) {
  const map = { minutes: 60, hours: 3600, days: 86400, weeks: 604800 };
  return Math.max(Number(value) || 1, 1) * (map[unit] || 3600) * 1000;
}

function nextRunAt(job) {
  return new Date(Date.now() + intervalToMs(job.interval_value, job.interval_unit)).toISOString();
}

function makeTitle(content, fallback) {
  const m = content.match(/<h1[^>]*>([\s\S]*?)<\/h1>/i);
  if (m) {
    const title = m[1].replace(/<[^>]+>/g, '').trim();
    if (title) {
      return title.slice(0, 200);
    }
  }
  return `${fallback} #${Math.floor(1000 + Math.random() * 9000)}`;
}

async function runJob(job) {
  const created = [];
  for (let i = 0; i < job.num_posts; i++) {
    try {
      const { content, model } = await ai.generatePost(job);
      const post = {
        id: `${Date.now()}-${i}`,
        job_id: job.id,
        title: makeTitle(content, job.job_name),
        content,
        model,
        status: job.post_status,
        created_at: new Date().toISOString(),
      };
      db.savePost(job.id, post);
      created.push(post);
    } catch (err) {
      console.error(`[BlogWriter] Job ${job.id} post ${i + 1} failed: ${err.message}`);
      break;
    }
  }

  const generated = Number(job.generated_posts) + created.length;
  db.updateJob(job.id, {
    generated_posts: generated,
    last_run: new Date().toISOString(),
    next_run: nextRunAt(job),
  });

  console.log(`[BlogWriter] Job ${job.id}: created ${created.length} post(s).`);
  return created;
}

async function tick() {
  if (running) {
    return;
  }
  running = true;
  try {
    const due = db.getDueJobs();
    for (const job of due) {
      await runJob(job);
    }
  } catch (err) {
    console.error(`[BlogWriter] Scheduler error: ${err.message}`);
  } finally {
    running = false;
  }
}

function startScheduler(tickMs) {
  if (timer) {
    return timer;
  }
  timer = setInterval(tick, tickMs || 60000);
  timer.unref && timer.unref();
  console.log(`[BlogWriter] Scheduler started (tick every ${tickMs || 60000}ms).`);
  return timer;
}

function stopScheduler() {
  if (timer) {
    clearInterval(timer);
    timer = null;
  }
}

module.exports = { startScheduler, stopScheduler, runJob, tick, intervalToMs, nextRunAt };
