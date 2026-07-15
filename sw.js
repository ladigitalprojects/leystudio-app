// Ley Studio — service worker: HTML siempre fresco (red primero), assets con caché
const CACHE='leystudio-v1';
const ASSETS=['/assets/logo.png','/assets/icon-192.png','/assets/icon-512.png','/assets/favicon.png'];
self.addEventListener('install',e=>{
  e.waitUntil(caches.open(CACHE).then(c=>c.addAll(ASSETS)).then(()=>self.skipWaiting()));
});
self.addEventListener('activate',e=>{
  e.waitUntil(caches.keys().then(ks=>Promise.all(ks.filter(k=>k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));
});
self.addEventListener('fetch',e=>{
  const url=new URL(e.request.url);
  if(url.origin!==location.origin) return; // CDNs y Firebase van directo
  if(e.request.mode==='navigate' || url.pathname.endsWith('.html') || url.pathname.endsWith('/')){
    // Red primero: siempre la última versión; caché solo si no hay conexión
    e.respondWith(
      fetch(e.request).then(r=>{
        const copy=r.clone();
        caches.open(CACHE).then(c=>c.put(e.request,copy));
        return r;
      }).catch(()=>caches.match(e.request))
    );
    return;
  }
  if(url.pathname.startsWith('/assets/')){
    e.respondWith(caches.match(e.request).then(hit=>hit||fetch(e.request).then(r=>{
      const copy=r.clone(); caches.open(CACHE).then(c=>c.put(e.request,copy)); return r;
    })));
  }
});
