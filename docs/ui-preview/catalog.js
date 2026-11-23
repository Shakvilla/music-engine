// Catalog snapshot used by the visual prototype. Track durations are rounded down to seconds.
const songs = tracks.map(([title, art, price], index) => ({
  title, art: artRoot + art + '.png', price,
  artist: 'John doe', album: index === 0 ? '' : 'Vanity',
  duration: ['3:01', '2:05', '3:01', '4:01', '2:35', '3:11'][index]
}));
const pages = {
  'home-screen': ['Home','Summary'], 'top-songs': ['Explore','Top Songs'],
  browse: ['Explore','Browse'], trending: ['Explore','New & Hot'],
  artists: ['Browse','Artists'], latest: ['Browse','Latest tracks'], artist: ['Artist','John doe'],
  album: ['Album','Vanity'], playlist: ['Playlist','Listen again'],
  albums: ['Collection','Albums'], playlists: ['Collection','Playlists'],
  search: ['Search','Search tracks'], likes: ['Collection','Likes'],
  history: ['Collection','History'], later: ['Collection','Listen Later'],
  store: ['Store','Browse the store'], purchased: ['Store','Purchased']
};
const search=document.createElement('input');
search.className='search'; search.type='search'; search.placeholder='Search tracks'; search.setAttribute('aria-label','Search tracks');
if (location.hash === '#search') search.value = new URLSearchParams(location.search).get('term') || '';
document.querySelector('.top .search').replaceWith(search);
search.addEventListener('input',()=>{
  if(location.hash!=='#search') location.hash='search';
  else renderMusic('search');
});
const menuButton=document.querySelector('.hamb');
const rail = document.querySelector('.side-rail');
const shade = document.querySelector('.drawer-shade');
const smallScreen = matchMedia('(max-width:760px)');
function syncRail() {
  const modal = smallScreen.matches && home.classList.contains('drawer');
  shade.hidden = !modal;
  document.querySelector('.stage').inert = modal;
  document.querySelector('.review').inert = modal;
  menuButton.setAttribute('aria-expanded', String(smallScreen.matches ? modal : !home.classList.contains('compact')));
}
function closeDrawer() {
  home.classList.remove('drawer');
  syncRail();
  menuButton.focus();
}
function markDestination(page) {
  const parent = {artists:'browse',feed:'home-screen',latest:'browse',artist:'browse',album:'albums',playlist:'playlists'};
  rail.querySelectorAll('a').forEach(a=>{
    if(a.hash === '#'+(parent[page] || page)) a.setAttribute('aria-current','page');
    else a.removeAttribute('aria-current');
  });
  if(home.classList.contains('drawer')) closeDrawer();
}
menuButton.onclick=()=>{
  home.classList.toggle(smallScreen.matches?'drawer':'compact');
  syncRail();
  if(!shade.hidden) rail.querySelector('.drawer-close').focus();
};
rail.querySelector('.drawer-close').onclick = closeDrawer;
shade.onclick = closeDrawer;
rail.addEventListener('click',event=>{if(event.target.closest('a') && !shade.hidden)closeDrawer()});
document.addEventListener('keydown',event=>{
  if(shade.hidden)return;
  if(event.key==='Escape')closeDrawer();
  if(event.key==='Tab'){
    const first=rail.querySelector('button'), last=rail.querySelector('a[href="#albums"]');
    if(event.shiftKey && document.activeElement===first){event.preventDefault();last.focus()}
    if(!event.shiftKey && document.activeElement===last){event.preventDefault();first.focus()}
  }
});
smallScreen.addEventListener('change',()=>{home.classList.remove('drawer','compact');syncRail()});
syncRail();
function link(page,label){return '<a href="#'+page+'">'+label+'</a>'}
function trackTable(list, page){
  if (!list.length) return '<p class="empty">'+(page==='search'?'No tracks match your search.':'No tracks here yet.')+'</p>';
  const showAlbum=page!=='album';
  const rows=list.map((song,i)=>'<tr><td class="number">'+String(i+1).padStart(2,'0')+'</td><td><div class="track-title"><img src="'+song.art+'" alt=""><span>'+song.title+'<small>'+song.artist+'</small></span></div></td><td class="by">'+link('artist',song.artist)+'</td>'+
    (showAlbum?'<td class="release">'+(song.album?link('album',song.album):'—')+'</td>':'')+
    '<td class="cost"><span>GH₵ '+song.price+'</span></td><td class="length">'+song.duration+'</td><td class="more"><details><summary aria-label="Actions for '+song.title+'">•••</summary><div>Play track<br>Like<br>Download</div></details></td></tr>').join('');
  return '<table aria-label="Songs"><thead><tr><th class="number">#</th><th>Title</th><th class="by">Artist</th>'+(showAlbum?'<th class="release">Album</th>':'')+'<th class="cost">Price</th><th class="length">Time</th><th class="more">More</th></tr></thead><tbody>'+rows+'</tbody></table>';
}
// Sample profiles illustrate the layout alongside the catalog's linked artist.
function artistGrid(){
  const artists=[
    ['Esi Vale',4],['Kojo Lane',2],['Ama Sol',3],
    ['Nii Grey',6],['Adwoa Blu',2]
  ];
  const samples=artists.map(([name,albums],i)=>{
    const position=(i%3)*50+'% '+Math.floor(i/3)*100+'%';
    return `<li><span class="artist-portrait" style="background-position:${position}"></span><strong>${name}</strong><small>${albums} albums</small></li>`;
  });
  samples.push('<li><a href="#artist"><img class="artist-portrait" src="../../Script/assets/images/avatar.png" alt=""><strong>John doe</strong><small>1 album</small></a></li>');
  return '<ul class="artist-grid">'+samples.join('')+'</ul>';
}
function covers(items){
  return '<div class="covers">'+items.map(([song,page,label])=>'<a href="#'+page+'"><img src="'+song.art+'" alt=""><strong>'+label+'</strong><small>'+song.artist+'</small></a>').join('')+'</div>';
}
function section(title,body,more=''){return '<section class="music-section"><header><h2>'+title+'</h2>'+more+'</header>'+body+'</section>'}
// Illustrative period snapshots and genres for the local design preview.
const chartEditions = {
  'This week': [0, 1, 2, 3, 4, 5],
  'Last week': [1, 2, 0, 4, 5, 3],
  'This month': [1, 0, 4, 3, 5, 2],
  'Last month': [4, 3, 1, 0, 2, 5],
  'This year': [3, 1, 4, 5, 0, 2]
};
const previewGenres = ['Afrobeats', 'Hip-Hop', 'Afrobeats', 'Highlife', 'Afrobeats', 'Afrobeats'];

function renderTopSongs() {
  const content = document.querySelector('#music-page');
  content.innerHTML = `
    <section class="song-chart">
      <header><h1>Top Songs</h1>
        <div class="song-filters">
          <select aria-label="Chart period">${Object.keys(chartEditions).map(period => '<option>'+period+'</option>').join('')}</select>
          <select aria-label="Genre">${['All genres', ...new Set(previewGenres)].map(genre => '<option>'+genre+'</option>').join('')}</select>
        </div>
      </header>
      <div class="song-columns"><ol aria-label="Song rankings"></ol><section class="song-detail" aria-label="Selected song" aria-live="polite"></section></div>
    </section>`;
  const [period, genre] = content.querySelectorAll('select');
  const list = content.querySelector('ol');
  const detail = content.querySelector('.song-detail');
  function selectTrack(button) {
    list.querySelectorAll('button').forEach(row => row.setAttribute('aria-pressed', String(row === button)));
    const index = Number(button.dataset.song);
    const song = songs[index];
    detail.innerHTML = `<img src="${song.art}" alt=""><div class="song-caption">
      <small>#${button.dataset.rank} · ${previewGenres[index]}</small>
      <h2>${song.title}</h2>${link('artist',song.artist)}
      <footer><span>${song.album ? link('album',song.album)+' · ' : ''}${song.duration}</span>${action('GH₵ '+song.price,'buy',index)}</footer>
    </div>`;
  }
  function updateRows() {
    const indices = chartEditions[period.value].filter(index => genre.value === 'All genres' || previewGenres[index] === genre.value);
    list.innerHTML = indices.map((index,i) => {
      const song = songs[index];
      return `<li><button type="button" data-song="${index}" data-rank="${i+1}">
        <span class="song-rank">${String(i+1).padStart(2,'0')}</span><img src="${song.art}" alt="">
        <span class="song-name"><strong>${song.title}</strong><small>${song.artist}</small></span>
        <span class="song-time">${song.duration}</span></button></li>`;
    }).join('');
    selectTrack(list.querySelector('button'));
  }
  list.onclick = event => {
    const button = event.target.closest('button');
    if (button) selectTrack(button);
  };
  period.onchange = genre.onchange = updateRows;
  updateRows();
  markDestination('top-songs');
}
function renderMusic(requested){
  if(['home-screen','feed'].includes(requested)){renderHome(requested);return}
  const page=Object.hasOwn(pages,requested)?requested:'top-songs';
  if (page === 'top-songs') { renderTopSongs(); return; }
  const [group,title]=pages[page];
  const chart=page==='trending';
  const heroArt=page==='album'?songs[1]:page==='playlist'?songs[4]:null;
  let body='';
  if(chart){
    body='<div class="list-tools"><span>'+link('trending','New & Hot')+' / '+link('top-songs','Top 50')+'</span><span>All genres</span></div>'+trackTable(songs,page);
  }else if(page==='artists'){
    body=artistGrid();
  }else if(page==='browse'){
    body='<nav class="subnav">'+link('latest','Latest tracks')+link('artists','Artists')+link('playlists','Playlists')+link('albums','Albums')+'</nav>'+
      section('This week’s top songs',trackTable(songs.slice(0,4),page),link('top-songs','View chart ↗'))+
      section('New & Hot',covers([[songs[0],'trending','Mani'],[songs[1],'trending','Vanity'],[songs[4],'trending',songs[4].title]]))+
      section('Top Artists',artistGrid());
  }else if(page==='albums'||page==='playlists'){
    body=covers(page==='albums'?[[songs[1],'album','Vanity']]:[[songs[4],'playlist','Listen again']]);
  }else{
    let list=songs;
    if(page==='album')list=songs.filter(song=>song.album);
    if(page==='playlist')list=[songs[4],songs[1],songs[0]];
    if(page==='latest')list=[...songs].reverse();
    if(['later','history','likes','purchased'].includes(page))list=[];
    if(page==='search')list=songs.filter(song=>(song.title+' '+song.artist+' '+song.album).toLowerCase().includes(search.value.toLowerCase().trim()));
    body=section(page==='artist'?'Top tracks':page==='album'||page==='playlist'?'Tracklist':'Tracks',trackTable(list,page));
    if(page==='artist')body+=section('Albums',covers([[songs[1],'album','Vanity']]));
  }
  const stack=chart?'<div class="sleeves" aria-hidden="true">'+[songs[4],songs[1],songs[0]].map(song=>'<img src="'+song.art+'" alt="">').join('')+'</div>':'';
  document.querySelector('#music-page').innerHTML='<header class="music-hero '+(chart?'chart-hero':'')+'">'+(heroArt?'<img class="cover-lead" src="'+heroArt.art+'" alt="">':'')+
    '<div><div class="crumb">'+group+'</div><h1>'+title+'</h1>'+(heroArt?'<p>'+link('artist','John doe')+'</p>':'')+'</div>'+stack+'</header>'+body;
  markDestination(page);
}
render();
