let chartPeriod = 'This week';
const homeDialog = document.createElement('dialog');
homeDialog.className = 'home-dialog';
homeDialog.setAttribute('aria-labelledby','home-dialog-title');
document.body.append(homeDialog);
const avatarPath = '../../Script/assets/images/avatar.png';
function homeNav(active) {
  return '<nav class="home-tabs" aria-label="Home views">'+[['home-screen','Summary'],['feed','Feed']].map(([route,label]) =>
    '<a href="#'+route+'" '+(active===route?'aria-current="page"':'')+'>'+label+'</a>').join('')+'</nav>';
}
function action(label,cmd,index) {
  return '<button type="button" data-home-action="'+cmd+'" data-index="'+index+'">'+label+'</button>';
}
function homeMenu(i) {
  return '<details class="home-more"><summary aria-label="More actions for '+songs[i].title+'">•••</summary><div>'+
    action('Add to playlist','playlist',i)+action('Listen later','later',i)+action('Download track','download',i)+action('Go to track ↗','track',i)+'</div></details>';
}
function waveform(i) {
  return '<div class="wave" aria-hidden="true">'+Array.from({length:80},(_,n)=>{
    const height=12+Math.abs(Math.sin(n*2.8+i)*Math.cos(n*.17)*38)+Math.abs(Math.sin(n*1.5)*31);
    return '<i style="height:'+height+'%"></i>';
  }).join('')+'</div>';
}
const feedGlyph = {
  play:'&#xf04b;', like:'&#xf004;', share:'&#xf08e;', download:'&#xf0ed;',
  cart:'&#xf217;', listeners:'&#xf025;', verified:'&#xf005;'
};
function icon(name) {
  return '<span aria-hidden="true" class="feed-icon">'+feedGlyph[name]+'</span>';
}
function feedButton(cmd,i,label,contents,style=''){
  return `<button class="${style}" data-home-action="${cmd}" data-index="${i}" aria-label="${label}" title="${label}">${contents}</button>`;
}
function feedPost(album){
  const i=album?1:0, song=songs[i], price=album?'299':song.price;
  const queue=album?`<div class="record-queue" role="group" aria-label="Album tracks">${songs.slice(1).map((track,j)=>
    `<div class="record-row"><img src="${track.art}" alt="">${feedButton('buy',j+1,'Buy '+track.title,icon('cart')+' GH₵'+track.price)}
    ${feedButton('play',j+1,'Play '+track.title,track.artist+' – '+track.title,'queue-song')}</div>`).join('')}</div>`:'';
  return `<article class="record-post"><header><img src="${avatarPath}" alt=""><span>${link('artist','John doe')} posted ${album?'an album':'a track'}</span><time datetime="2026-11-16">4d ago</time></header>
    <div class="record-layout"><div class="record-art"><img src="${song.art}" alt="${song.title} cover">
    ${feedButton(album?'buyAlbum':'buy',i,'Buy '+song.title+' for GH₵'+price,icon('cart')+' Buy Now (GH₵'+price+')','record-buy')}</div>
    <div class="record-main"><div class="record-heading">${feedButton('play',i,'Play '+song.title,icon('play'),'record-play')}
      <div>${link('artist','John doe')} <span class="verified" title="Verified artist">${icon('verified')}</span><h2>${album?'vanity':song.title}</h2></div>
      ${album?'':'<span class="record-tag">#fadlan</span>'}</div>
      <div class="record-wave">${waveform(i)}<div class="reflection">${waveform(i)}</div><time>0${song.duration}</time></div>${queue}
      <footer>${feedButton('like',i,'Like',icon('like'))}${feedButton('share',i,'Share',icon('share'))}${feedButton('download',i,'Download',icon('download'))}
      ${album?'':homeMenu(i)+'<small>'+icon('listeners')+' 1</small>'}</footer></div></div></article>`;
}
function releaseFeature() {
  const album = songs[1];
  return `<section class="release-feature">
    <div class="release-caption"><h2>New Albums</h2><a class="release-name" href="#album">Vanity</a>
      <p>${link('artist',album.artist)} <span>·</span> 5 tracks</p>
      <a class="album-open" href="#album">View album ↗</a></div>
    <a class="release-sleeve" href="#album" aria-label="Open Vanity album"><img src="${album.art}" alt="Vanity cover"></a>
  </section>`;
}
function renderHome(page) {
  const feed=page==='feed';
  markDestination(page);
  const content=document.querySelector('#music-page');
  content.innerHTML='<div class="home-title"><h1>Home</h1>'+homeNav(page)+'</div>';
  if(feed) {
    content.insertAdjacentHTML('beforeend','<div class="feed-columns"><div class="feed-stream"><h2>Following</h2>'+feedPost(true)+feedPost(false)+'</div>'+
      '<section class="profile-status"><img src="'+avatarPath+'" alt=""><h3>'+link('artist','John doe')+'</h3><label for="profile-progress">Profile completion <b>60%</b></label><progress id="profile-progress" value="60" max="100"></progress></section></div>');
  }else{
    content.insertAdjacentHTML('beforeend',releaseFeature()+'<section class="summary-chart"><header><h2>Top Songs</h2><div class="chart-periods">'+['This week','This month','This year'].map(p=>'<button aria-pressed="'+(p===chartPeriod)+'" data-period="'+p+'">'+p+'</button>').join('')+'</div></header>'+
      trackTable(songs,page)+'</section>');
    content.querySelectorAll('tbody tr').forEach((row,i)=>{
      const more=row.querySelector('.more');
      more.innerHTML=homeMenu(i);
      row.querySelector('.cost').innerHTML=action('GH₵ '+songs[i].price,'buy',i);
      const art=row.querySelector('.track-title img');
      art.outerHTML='<button class="cover-play" aria-label="Play '+songs[i].title+'" data-home-action="play" data-index="'+i+'">'+art.outerHTML+'<span>▶</span></button>';
    });
  }
}
function showHomeDialog(cmd,i){
  const song=songs[i], title={playlist:'Add to playlist',newlist:'Create playlist',share:'Share',embed:'Embed',message:'Message',track:song.title,buy:'Buy track',buyAlbum:'Buy album',download:'Download track',later:'Listen later'}[cmd];
  let body='';
  if(['share','embed','message'].includes(cmd)){
    body='<nav>'+['share','embed','message'].map(t=>action(t[0].toUpperCase()+t.slice(1),t,i)).join('')+'</nav>';
    if(cmd==='share')body+='<div class="share-cover"><img src="'+song.art+'" alt=""><strong>'+song.title+'<small>'+song.artist+'</small></strong></div><label>Share URL<input readonly value="http://localhost:8082/?p=track/'+(423+i)+'-'+song.title.toLowerCase().replaceAll(' ','-')+'"></label>';
    if(cmd==='embed')body+='<label>Widget<select><option>Picture widget</option><option>Classic widget</option><option>Mini widget</option></select></label><div class="embed-sample"><b>'+song.title+'</b>'+waveform(i)+'</div><label>Color <input type="color" value="#00b263"></label><label><input type="checkbox"> Autoplay</label>';
    if(cmd==='message')body+='<label>Recipient<input placeholder="Search people"></label><label>Message<textarea></textarea></label>';
  }else if(cmd==='playlist'||cmd==='newlist'){
    body='<nav>'+action('Playlist / Album','playlist',i)+action('Add playlist','newlist',i)+'</nav>'+(cmd==='playlist'?'<button class="picker-item" data-pick><img src="'+songs[1].art+'" alt=""><span><b>Vanity</b><small>Album · 5 tracks</small></span><strong>＋</strong></button>':'<label>Playlist name<input></label>');
  }else body='<div class="dialog-track"><img src="'+song.art+'" alt=""><div><b>'+song.title+'</b><small>John doe</small></div></div>';
  if(cmd==='buy'||cmd==='buyAlbum')body+='<div class="order-line"><span>Total</span><b>GH₵ '+(cmd==='buyAlbum'?'299':song.price)+'</b></div>';
  if(cmd==='track')body+=waveform(i);
  homeDialog.innerHTML='<header><h2 id="home-dialog-title">'+title+'</h2><button aria-label="Close" data-dismiss>×</button></header>'+body;
  if(!homeDialog.open)homeDialog.showModal();
}
document.addEventListener('click',event=>{
  const period=event.target.closest('[data-period]');
  if(period){chartPeriod=period.dataset.period;renderHome('home-screen');document.querySelector('[aria-pressed="true"][data-period]').focus();return}
  const pick=event.target.closest('[data-pick]');
  if(pick){pick.setAttribute('aria-pressed','true');pick.querySelector('strong').textContent='✓';return}
  if(event.target.closest('[data-dismiss]')){homeDialog.close();return}
  const control=event.target.closest('[data-home-action]');
  if(!control)return;
  const cmd=control.dataset.homeAction,i=Number(control.dataset.index);
  if(cmd==='like'||cmd==='play'){
    const on=control.getAttribute('aria-pressed')!=='true';
    control.setAttribute('aria-pressed',String(on));
    if(cmd==='like')control.setAttribute('aria-label',on?'Unlike':'Like');
    else if(control.classList.contains('cover-play'))control.querySelector('span').textContent=on?'Ⅱ':'▶';
    else if(control.classList.contains('record-play')){control.innerHTML=on?'Ⅱ':icon('play');control.setAttribute('aria-label',(on?'Pause ':'Play ')+songs[i].title)}
    return;
  }
  showHomeDialog(cmd,i);
});
