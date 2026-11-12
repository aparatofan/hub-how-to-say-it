const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('assets/hub.js','utf8').replace('  boot();', '  window.testAPI = { S, route, viewHome, viewFinal, viewFinalPlay, viewFinalKey, applyState, render, loadFinal, finalAction, finalCommit, finalNext };');
const php=fs.readFileSync('data/v1-final-test.php','utf8');
const items=JSON.parse(php.split("<<<'JSON'\n")[1].split('\nJSON\n')[0]);

function setup(hash='#/',reader={name:'Test',email:'test@example.test',volumes:[1]}) {
 const app={innerHTML:'',addEventListener(){},querySelectorAll(){return []},querySelector(){return null}};
 const window={HTSI_CONFIG:{api:'/api'},addEventListener(){},scrollTo(){},matchMedia(){return {matches:true}}};
 const location={hash,replace(h){location.hash=h;}};
 const context={window,document:{getElementById(){return app},addEventListener(){}},location,fetch(){throw Error('Unexpected network request')},console,setTimeout};
 vm.runInNewContext(source,context);
 const a=window.testAPI;
 a.applyState({reader,volumes:[1,2,3].map(n=>({n,name:'Volume '+n,chapters:Array.from({length:10},(_,i)=>({n:i+1,title:'Chapter '+(i+1),map:true,games:0}))}))});
 a.S.loaded=true;
 return {a,app,context};
}
const tick=()=>new Promise(r=>setImmediate(r));

test('100 items: four rounds of 25, ten per chapter, half correct',()=>{
 assert.deepEqual(items.map(i=>i.n),Array.from({length:100},(_,i)=>i+1));
 for(const r of [1,2,3,4])assert.equal(items.filter(i=>i.r===r).length,25);
 for(let ch=1;ch<=10;ch++)assert.equal(items.filter(i=>i.ch===ch).length,10);
 assert.equal(items.filter(i=>i.ok).length,50);
 assert.equal(items.filter(i=>i.type==='wrong fit').length,35);
 assert.equal(items.filter(i=>i.type==='form slip').length,15);
 for(const i of items){assert.ok(i.a&&i.b&&i.note);assert.equal(!!i.fix,!i.ok);}
});
test('final test row sits after chapter 10 and only opens with Volume 1',()=>{
 const {a}=setup();const html=a.viewHome();
 assert.ok(html.indexOf('href="#/1/10"')<html.indexOf('href="#/1/10/final"'));
 assert.equal((html.match(/class="htsi-row-num"/g)||[]).length,10);
 a.S.vol=2;assert.ok(!a.viewHome().includes('THE FINAL TEST'));
 const locked=setup('#/',{volumes:[2]});assert.ok(!locked.a.viewHome().includes('href="#/1/10/final"'));
});
test('routes: overview, play and key',()=>{
 const {a,context}=setup('#/1/10/final');assert.equal(a.route().name,'final');
 context.location.hash='#/1/10/final/play';assert.equal(a.route().name,'final-play');
 context.location.hash='#/1/10/final/key';assert.equal(a.route().name,'final-key');
 context.location.hash='#/1/9/final';assert.equal(a.route().name,'chapter');
});
test('signed-out and locked deep links never load the test',()=>{
 for(const reader of [null,{volumes:[2]}]){const {a,app}=setup('#/1/10/final',reader);a.render();assert.equal(a.S.final,null);assert.ok(!app.innerHTML.includes('final-start'));}
});
test('overview shows saved rounds, total and practice link',()=>{
 const {a}=setup('#/1/10/final');
 a.S.final={items,progress:{1:{score:20,total:25,wrong:[1,5,8,11,13]},3:{score:25,total:25,wrong:[]}}};
 const html=a.viewFinal();
 assert.ok(html.includes('45<span> / 50</span>'));assert.ok(html.includes('2 of 4 rounds done'));
 assert.ok(html.includes('Practise your 5 mistakes'));assert.ok(html.includes('Start Round 2'));
 assert.equal((html.match(/data-act="final-start"/g)||[]).length,4);
});
test('playing a round scores answers and saves the finished round',async()=>{
 const {a,context}=setup('#/1/10/final');a.S.final={items,progress:{}};
 let sent;context.fetch=async(url,opt)=>{sent={url,body:JSON.parse(opt.body)};return {ok:true,json:async()=>({progress:{4:{score:24,total:25,wrong:[76]}}})};};
 a.finalAction('final-start',{getAttribute:()=> '4'});
 assert.equal(a.route().name,'final-play');assert.equal(a.S.finalPlay.items.length,25);
 const round=a.S.finalPlay.items;
 round.forEach((it,i)=>{a.finalCommit(i===0?(it.ok?'down':'up'):(it.ok?'up':'down'));a.finalNext();});
 assert.equal(a.S.finalPlay.done,true);assert.equal(a.S.finalPlay.missed.length,1);
 await tick();
 assert.equal(sent.url,'/api/final-test/round');assert.equal(sent.body.round,4);
 assert.equal(Object.keys(sent.body.answers).length,25);assert.equal(a.S.final.progress[4].score,24);
 assert.ok(a.viewFinalPlay().includes('24<span> / 25</span>'));
});
test('a card cannot be answered twice, and Next only works after answering',()=>{
 const {a}=setup('#/1/10/final');a.S.final={items,progress:{}};
 a.finalAction('final-start',{getAttribute:()=> '1'});
 a.finalNext();assert.equal(a.S.finalPlay.i,0);
 a.finalCommit('up');a.finalCommit('down');
 assert.equal(a.S.finalPlay.answers[1],true);
 a.finalNext();assert.equal(a.S.finalPlay.i,1);assert.equal(a.S.finalPlay.revealed,false);
});
test('practice rounds use saved mistakes and are never saved',async()=>{
 const {a,context}=setup('#/1/10/final');a.S.final={items,progress:{2:{score:23,total:25,wrong:[26,40]}}};
 context.fetch=()=>{throw Error('practice must not save');};
 a.finalAction('final-practice',{getAttribute:()=> '2'});
 assert.deepEqual(a.S.finalPlay.items.map(i=>i.n),[26,40]);assert.equal(a.S.finalPlay.practice,true);
 a.finalCommit(items[25].ok?'up':'down');a.finalNext();a.finalCommit(items[39].ok?'up':'down');a.finalNext();
 await tick();assert.equal(a.S.finalPlay.done,true);assert.ok(a.viewFinalPlay().includes('Practice done'));
});
test('key lists all 100 items with verdicts and fixes',()=>{
 const {a}=setup('#/1/10/final/key');a.S.final={items,progress:{}};
 const html=a.viewFinalKey();
 assert.equal((html.match(/QUESTION \d+/g)||[]).length,100);
 assert.equal((html.match(/class="htsi-fpill is-ok"/g)||[]).length,50);
 assert.equal((html.match(/class="htsi-ffix"/g)||[]).length,50);
});
test('logout clears test data and the round in progress',()=>{
 const {a}=setup();a.S.final={items,progress:{}};a.S.finalPlay={done:false};a.applyState({});
 assert.equal(a.S.final,null);assert.equal(a.S.finalPlay,null);
});
test('a late reply after logout does not bring the test back',async()=>{
 const {a,context}=setup('#/1/10/final');let resolve;
 context.fetch=()=>new Promise(r=>{resolve=r});a.loadFinal();a.applyState({});
 resolve({ok:true,json:async()=>({items,progress:{}})});await tick();assert.equal(a.S.final,null);
});
test('reloading on the play route without a round goes back to the overview',()=>{
 const {a,context}=setup('#/1/10/final/play');a.S.final={items,progress:{}};
 a.viewFinalPlay();assert.equal(context.location.hash,'#/1/10/final');
});
