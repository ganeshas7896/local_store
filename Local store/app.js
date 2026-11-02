function getLoc(cb){const m=document.querySelector('.locmsg');if(!navigator.geolocation){if(m)m.textContent='Location not supported';return}
navigator.geolocation.getCurrentPosition(p=>{document.querySelectorAll('.lat').forEach(i=>i.value=p.coords.latitude);document.querySelectorAll('.lng').forEach(i=>i.value=p.coords.longitude);if(m)m.textContent='Location captured ✔';if(cb)cb(p.coords)},()=>{if(m)m.textContent='Allow location access'},{enableHighAccuracy:true,timeout:15000,maximumAge:60000})}
function toggleImg(select){const options=select.closest('.image-options');const isUrl=select.value==='url';options.querySelector('.image-url').style.display=isUrl?'block':'none';options.querySelector('.image-file').style.display=isUrl?'none':'block'}

document.querySelectorAll('[data-history]').forEach(button=>{
    button.addEventListener('click',()=>{
        if(button.dataset.history==='back')history.back();
        else if(button.dataset.history==='forward')history.forward();
    });
});

const themeToggle=document.getElementById('theme-toggle');
if(themeToggle){
    const themeIcon=themeToggle.querySelector('span');
    const updateThemeButton=()=>{
        const dark=document.documentElement.dataset.theme==='dark';
        themeToggle.setAttribute('aria-label',dark?'Switch to light mode':'Switch to dark mode');
        themeToggle.title=dark?'Switch to light mode':'Switch to dark mode';
        if(themeIcon)themeIcon.textContent=dark?'☀':'☾';
    };
    updateThemeButton();
    themeToggle.addEventListener('click',()=>{
        const theme=document.documentElement.dataset.theme==='dark'?'light':'dark';
        document.documentElement.dataset.theme=theme;
        try{localStorage.setItem('localstore-theme',theme)}catch(error){}
        updateThemeButton();
    });
}

if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches&&'IntersectionObserver'in window){
    document.body.classList.add('motion-ready');
    const revealItems=document.querySelectorAll('main h2,main h3,.card,.panel,form.box,table,.role-entry,.nearby-form,.discover-heading');
    const observer=new IntersectionObserver(entries=>{
        entries.forEach(entry=>{
            if(entry.isIntersecting){
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    },{threshold:0.08});
    revealItems.forEach((item,index)=>{
        item.classList.add('reveal');
        item.style.setProperty('--reveal-delay',`${Math.min(index%6,5)*55}ms`);
        observer.observe(item);
    });
}
