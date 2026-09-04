'use strict';

const $ = (sel) => document.querySelector(sel);

async function api(path, options) {
  const headers = { 'Content-Type': 'application/json' };
  const res = await fetch(`/api${path}`, { ...options, headers });
  const body = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error(body.message || `Request failed (${res.status})`);
  }
  return body;
}

function toast(msg) {
  const el = $('#toast');
  el.textContent = msg;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 3000);
}

function setMsg(id, text, ok) {
  const el = $(id);
  el.textContent = text;
  el.className = 'msg ' + (ok ? 'ok' : text ? 'err' : '');
}

function statusBadge(status) {
  return `<span class="badge ${status}">${status}</span>`;
}

async function refreshKeyStatus() {
  try {
    const { settings } = await api('/settings');
    const dot = $('#keyDot');
    const label = $('#keyLabel');
    dot.className = 'dot ' + (settings.hasApiKey ? 'ok' : 'missing');
    label.textContent = settings.hasApiKey ? 'API key configured' : 'API key missing';
    return settings;
  } catch (err) {
    $('#keyLabel').textContent = 'offline';
    return null;
  }
}

async function refreshStats(jobs) {
  const running = jobs.filter((j) => j.status === 'running').length;
  const posts = jobs.reduce((s, j) => s + Number(j.generated_posts || 0), 0);
  $('#statJobs').textContent = jobs.length;
  $('#statRunning').textContent = running;
  $('#statPosts').textContent = posts;
}

async function loadJobs() {
  try {
    const { jobs } = await api('/jobs');
    const tbody = $('#jobsBody');
    refreshStats(jobs);

    if (!jobs.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="muted">No jobs yet. Create your first job.</td></tr>';
      return;
    }

    tbody.innerHTML = jobs
      .map((j) => {
        const pauseResume =
          j.status === 'running'
            ? `<button class="btn small" data-act="pause" data-id="${j.id}">Pause</button>`
            : `<button class="btn small primary" data-act="resume" data-id="${j.id}">Resume</button>`;
        return `<tr>
          <td>${j.id}</td>
          <td><strong>${escapeHtml(j.job_name)}</strong></td>
          <td>${statusBadge(j.status)}</td>
          <td>${j.generated_posts || 0} / ${j.num_posts}</td>
          <td>${j.interval_value} ${j.interval_unit}</td>
          <td>${j.next_run ? fmtDate(j.next_run) : '-'}</td>
          <td>
            ${pauseResume}
            <button class="btn small" data-act="run" data-id="${j.id}">Run Now</button>
            <button class="btn small danger" data-act="delete" data-id="${j.id}">Delete</button>
          </td>
        </tr>`;
      })
      .join('');
  } catch (err) {
    $('#jobsBody').innerHTML = `<tr><td colspan="7" class="msg err">${escapeHtml(err.message)}</td></tr>`;
  }
}

function fmtDate(iso) {
  const d = new Date(iso);
  return isNaN(d) ? iso : d.toLocaleString();
}

function escapeHtml(str) {
  return String(str == null ? '' : str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

async function jobAction(id, action) {
  try {
    const body = await api(`/jobs/${id}/${action}`, { method: 'POST' });
    toast(body.message || `Job ${action}d.`);
    await loadJobs();
  } catch (err) {
    toast(err.message);
  }
}

async function createJob() {
  const payload = {
    job_name: $('#job_name').value.trim(),
    brand_name: $('#brand_name').value.trim(),
    url: $('#url').value.trim(),
    requirements: $('#requirements').value.trim(),
    num_posts: Number($('#num_posts').value),
    word_count: Number($('#word_count').value),
    tone: $('#tone').value,
    seo_keywords: $('#seo_keywords').value.trim(),
    interval_value: Number($('#interval').value),
    interval_unit: $('#interval_unit').value,
    post_status: $('#post_status').value,
  };

  if (!payload.job_name) {
    setMsg('#newJobMsg', 'Job name is required.', false);
    return;
  }

  try {
    const { job } = await api('/jobs', { method: 'POST', body: JSON.stringify(payload) });
    setMsg('#newJobMsg', `Job #${job.id} created.`, true);
    await jobAction(job.id, 'resume');
    switchView('jobs');
  } catch (err) {
    setMsg('#newJobMsg', err.message, false);
  }
}

async function saveSettings() {
  const payload = { apiKey: $('#apiKey').value.trim(), model: $('#model').value.trim() };
  try {
    const { settings } = await api('/settings', { method: 'POST', body: JSON.stringify(payload) });
    setMsg('#settingsMsg', settings.hasApiKey ? 'API key saved (encrypted).' : 'Saved.', true);
    $('#apiKey').value = '';
    await refreshKeyStatus();
  } catch (err) {
    setMsg('#settingsMsg', err.message, false);
  }
}

async function runOcr() {
  const input = $('#ocrFile');
  if (!input.files || !input.files[0]) {
    setMsg('#ocrMsg', 'Choose a file first.', false);
    return;
  }
  const form = new FormData();
  form.append('document', input.files[0]);
  try {
    const res = await fetch('/api/ocr', { method: 'POST', body: form });
    const body = await res.json();
    if (!res.ok || !body.success) {
      throw new Error(body.message || 'OCR failed.');
    }
    $('#ocrResult').value = body.text;
    setMsg('#ocrMsg', `Extracted ${body.chars} characters.`, true);
  } catch (err) {
    setMsg('#ocrMsg', err.message, false);
  }
}

async function loadModels() {
  try {
    const { settings } = await api('/settings');
    $('#model').value = settings.model || '';
    const res = await fetch('/api/status');
    const body = await res.json();
    const models = (body && body.api && body.api.models) || fallbackModels();
    $('#modelsList').innerHTML = models.map((m) => `<li>${escapeHtml(m)}</li>`).join('');
  } catch (err) {
    $('#modelsList').innerHTML = fallbackModels().map((m) => `<li>${escapeHtml(m)}</li>`).join('');
  }
}

function fallbackModels() {
  return [
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
}

function switchView(name) {
  document.querySelectorAll('.tab').forEach((t) => t.classList.toggle('active', t.dataset.view === name));
  document.querySelectorAll('.view').forEach((v) => v.classList.toggle('active', v.id === `view-${name}`));
  if (name === 'jobs') loadJobs();
  if (name === 'settings') loadModels();
}

// Events
document.querySelectorAll('.tab').forEach((t) => t.addEventListener('click', () => switchView(t.dataset.view)));
document.querySelectorAll('[data-goto]').forEach((b) => b.addEventListener('click', () => switchView(b.dataset.goto)));
$('#createJob').addEventListener('click', createJob);
$('#saveSettings').addEventListener('click', saveSettings);
$('#ocrBtn').addEventListener('click', runOcr);
$('#jobsBody').addEventListener('click', (e) => {
  const btn = e.target.closest('button[data-act]');
  if (!btn) return;
  if (btn.dataset.act === 'delete' && !confirm('Delete this job?')) return;
  jobAction(btn.dataset.id, btn.dataset.act);
});

// Boot
(async function init() {
  await refreshKeyStatus();
  await loadJobs();
  await loadModels();
})();
