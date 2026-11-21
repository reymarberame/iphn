const key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImFvYnFhemV5dnd3dnFhY2Zld2xhIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc5NTIxMjM2NywiZXhwIjoyMTEwNzg4MzY3fQ.ADUaHUZ823bmN9aGTmyDTi2NIpHM3h7UezGADRJzbRc';
const base = 'https://aobqazeyvwwvqacfewla.supabase.co/realtime/v1/api/broadcast/test-node/events/ping';

const variants = [
  ['query+headers', `${base}?apikey=${key}&vsn=2.0.0`, { apikey: key, Authorization: `Bearer ${key}`, 'Content-Type': 'application/json' }],
  ['headers-only', base, { apikey: key, Authorization: `Bearer ${key}`, 'Content-Type': 'application/json' }],
  ['query-only', `${base}?apikey=${key}&vsn=2.0.0`, { 'Content-Type': 'application/json' }],
];

for (const [name, url, headers] of variants) {
  const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify({ hello: 'direct' }) });
  console.log(name, res.status, (await res.text()).slice(0, 100));
}
