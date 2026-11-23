const artwork = 'artwork/';
const week = [
  ['Mani','423-mani','c2c764b3dc782a33dee3fb0ed066d37f','3:01','9.99'],
  ['Vanity','424-vanity','6435265ec402527db8f3c1296ae331ba','2:05','0.90'],
  ['Mani','425-mani','62e6902e575cc5117b96532fb61bd24e','3:01','0.90'],
  ['Kofi-Kinaata-Boot-4-Boot','426-kofi-kinaata-boot-4-boot','fb8961fc9fad0800ed8d9b55eee47f18','4:01','0.90'],
  ['Seyi-Vibez-Alaska','427-seyi-vibez-alaska','ab46c0f8ed14f7add7481a9f33a60c53','2:35','0.90'],
  ['Buddy DML - Emerey (Mastered)','428-buddy-dml-emerey-mastered','ef4c9061ff41da9d53f519195de06670','3:11','0.90']
];
const app='http://localhost:8082/?p=';
const picture=track=>artwork+track[2]+'.png';
const trackUrl=track=>app+'track/'+track[1];
const atlasPosition=i=>(i%3)*50+'% '+Math.floor(i/3)*100+'%';
function releaseCard(track) {
  return `<a class="card" href="${trackUrl(track)}"><div class="sleeve"><img alt="" src="${picture(track)}"><span class="price">GH₵${track[4]}</span><span class="open" aria-hidden="true">↗</span></div><strong>${track[0]}</strong><small>John doe · ${track[3]}</small></a>`;
}
document.querySelector('#new-grid').innerHTML=[week[5],week[4],week[3],week[2]].map(releaseCard).join('');
document.querySelector('#hot-grid').innerHTML=week.map(releaseCard).join('');
const upcoming=[['Sun Room','Esi Vale'],['Blue Hour','Nii Grey'],['Drum Talk','Kojo Lane'],['Brass Notes','The North Trio'],['Violet Tide','Adwoa Blu'],['Coastline','Ama Sol']];
document.querySelector('#upcoming-grid').innerHTML=upcoming.map(([name,artist],i)=>
  `<article class="card"><div class="sleeve art" style="background-position:${atlasPosition(i)}"></div><strong>${name}</strong><small>${artist}</small></article>`).join('');
const features=[
  ['Find your next favourite.','Dig into new releases, follow the charts, and buy music directly from artists.','Browse catalogue','index.html#browse',[2,1,0]],
  ['Vanity','Explore the five-track album from John doe.','Explore album','index.html#album',[week[0],week[4],week[1]]],
  ['This week’s top songs.','Hear the tracks leading the BeatzClik chart.','Explore Top 50','index.html#top-songs',[1,4,3]]
];
let active=0;
function showFeature(){
  const [heading,copy,label,url,covers]=features[active];
  document.querySelector('.hero h1').textContent = heading;
  document.querySelector('#position').textContent = (active + 1) + ' / ' + features.length;
  document.querySelector('.hero-copy p').textContent=copy;
  const action=document.querySelector('.hero-link');action.href=url;action.textContent=label;
  document.querySelectorAll('.stack div').forEach((el,i)=>{
    const cover=covers[i],sample=typeof cover==='number';
    el.style.backgroundImage=sample?'url(landing-sleeves.jpg)':'url('+picture(cover)+')';
    el.style.backgroundSize=sample?'300% 200%':'cover';
    el.style.backgroundPosition=sample?atlasPosition(cover):'center';
  });
}
for(const [selector,step] of [['.prev',-1],['.next',1]])document.querySelector(selector).onclick=()=>{active=(active+step+features.length)%features.length;showFeature()};
showFeature();
const ranking=document.querySelector('#ranking');
ranking.innerHTML=week.map((t,i)=>`<li><span>${i+1}</span><a href="${trackUrl(t)}"><img alt="" src="${picture(t)}"><div><strong>${t[0]}</strong><small>John doe</small></div></a><em>GH₵${t[4]}</em></li>`).join('');
document.querySelectorAll('.periods button').forEach(button=>button.onclick=()=>{
  document.querySelectorAll('.periods button').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));
  ranking.setAttribute('aria-label','Top songs · '+button.textContent);
});
const directoryLinks = [
  ['New & Hot', 'index.html#trending', 'f201'],
  ['Top 50', 'index.html#top-songs', 'f080'],
  ['Latest tracks', 'index.html#latest', 'f017'],
  ['Albums', 'index.html#albums', 'f02d'],
  ['Artists', 'index.html#artists', 'f130'],
  ['Playlists', 'index.html#playlists', 'f03a'],
  ['Store', 'index.html#store', 'f290'],
  ['Videos', app + 'videos', 'f03d']
];
document.querySelector('.music-directory').innerHTML = `
  <a class="directory-album" href="index.html#album">
    <img src="${picture(week[1])}" alt="">
    <div><small>Featured album</small><strong>Vanity</strong><span>John doe · 5 tracks</span></div>
    <b aria-hidden="true">↗</b>
  </a>
  <div class="directory-links">${directoryLinks.map(([label, href, icon]) =>
    `<a href="${href}"><i aria-hidden="true">&#x${icon};</i>${label}</a>`
  ).join('')}</div>`;
const genres=['Classical','Comedy','Hip-hop','Jazz','Pop','Reggae'];
const symbols=['f001','f118','f130','f025','f005','f185'];
document.querySelector('.genres').innerHTML=genres.map((g,i)=>`<a href="${app}discover/genre/${i+1}-${g.toLowerCase()}"><i aria-hidden="true">&#x${symbols[i]};</i>${g}</a>`).join('');

const artistShowcase = [['Esi Vale',4],['Kojo Lane',2],['Ama Sol',3],['Nii Grey',6],['Adwoa Blu',2]];
const portraitCards = artistShowcase.map(([name, albums], i) =>
  `<div><span class="artist-disc" style="background-position:${atlasPosition(i)}"></span><strong>${name}</strong><small>${albums} albums</small></div>`
);
portraitCards.push('<a href="index.html#artist"><img class="artist-disc" src="../../Script/assets/images/avatar.png" alt=""><strong>John doe</strong><small>1 album</small></a>');
document.querySelector('.bc-artists').innerHTML = portraitCards.join('');
