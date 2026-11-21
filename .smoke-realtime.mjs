import { createClient } from '@supabase/supabase-js';

const url = 'https://aobqazeyvwwvqacfewla.supabase.co';
const key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImFvYnFhemV5dnd3dnFhY2Zld2xhIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc5NTIxMjM2NywiZXhwIjoyMTEwNzg4MzY3fQ.ADUaHUZ823bmN9aGTmyDTi2NIpHM3h7UezGADRJzbRc';

const originalFetch = globalThis.fetch;
globalThis.fetch = async (input, init) => {
  console.log('REQUEST', String(input));
  const h = new Headers(init?.headers);
  for (const [k, v] of h.entries()) console.log('H', k, '=', v);
  console.log('BODY', init?.body);
  return originalFetch(input, init);
};

const supabase = createClient(url, key, { auth: { persistSession: false, autoRefreshToken: false } });
const channel = supabase.channel('test-node');
console.log('RESULT', await channel.httpSend('ping', { hello: 'node' }));
supabase.removeChannel(channel);
