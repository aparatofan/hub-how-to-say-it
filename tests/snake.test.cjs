const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('assets/hub.js','utf8').replace('  boot();', '  window.testAPI = { S, route, viewHome, viewSnake, applyState, render, loadSnake, snakeAction };');
const php=fs.readFileSync('data/v1c1-3-snake.php','utf8');
const items=JSON.parse(php.split("<<<'JSON'\n")[1].split('\nJSON\n')[0]);
function setup(hash='#/',reader={name:'Test',email:'test@example.test',volumes:[1]}) {
 const app={innerHTML:'',addEventListener(){},querySelectorAll(){return []},querySelector(){return null}};
 const window={HTSI_CONFIG:{api:'/api'},addEventListener(){},scrollTo(){}};
 const context={window,document:{getElementById(){return app}},location:{hash},fetch(){throw Error('Unexpected network request')},console};
 vm.runInNewContext(source,context);
 const a=window.testAPI;
 a.applyState({reader,volumes:[1,2,3].map(n=>({n,name:'Volume '+n,chapters:Array.from({length:10},(_,i)=>({n:i+1,title:'Chapter '+(i+1),map:true,games:0}))}))});
 a.S.loaded=true;
 return {a,app,context};
}
test('42 unique tasks, balanced chapters and four task types',()=>{
 assert.equal(items.length,42);assert.equal(new Set(items.map(i=>i.n)).size,42);
 for(const ch of [1,2,3])assert.equal(items.filter(i=>i.ch===ch).length,14);
 assert.equal(new Set(items.map(i=>i.tl)).size,4);
 for(const i of items)assert.ok(i.q&&i.a&&i.note);
});
test('revision row sits after 03 before 04 without changing chapter numbers',()=>{
 const {a}=setup();const html=a.viewHome();
 assert.ok(html.indexOf('href="#/1/3"')<html.indexOf('href="#/1/3/snake"'));
 assert.ok(html.indexOf('href="#/1/3/snake"')<html.indexOf('href="#/1/4"'));
 assert.equal((html.match(/class="htsi-row-num"/g)||[]).length,10);
 a.S.vol=2;assert.ok(!a.viewHome().includes('THE SNAKE'));
});
test('locked Volume 1 has no actionable game link',()=>{
 const {a}=setup('#/',{volumes:[2]});assert.ok(!a.viewHome().includes('href="#/1/3/snake"'));
});
test('snake deep link is routed independently of chapter 3',()=>{
 const {a}=setup('#/1/3/snake');assert.equal(a.route().name,'snake');
 a.S.snake={3:{items}};a.render();assert.equal((a.viewSnake().match(/data-act="snake-open"/g)||[]).length,42);
 assert.ok(!a.S.chapters['1-3']);
});
test('signed-out deep link prompts sign-in; locked deep link offers volume unlock',()=>{
 const signedOut=setup('#/1/3/snake',null);signedOut.a.render();assert.ok(signedOut.app.innerHTML.includes('data-form="login"'));
 const locked=setup('#/1/3/snake',{volumes:[2]});locked.a.render();assert.ok(locked.app.innerHTML.includes('data-form="unlock"'));assert.equal(Object.keys(locked.a.S.snake).length,0);
});
test('logout clears game data and progress',()=>{
 const {a}=setup();a.S.snake={3:{items}};a.S.snakeSeen={3:{1:true},6:{2:true}};a.applyState({});assert.equal(Object.keys(a.S.snake).length,0);assert.equal(Object.keys(a.S.snakeSeen).length,0);
});
test('load error exposes a retry action',()=>{const {a}=setup('#/1/3/snake');a.S.snake={3:{error:'Offline'}};assert.ok(a.viewSnake().includes('data-act="snake-retry"'));});
test('late API reply cannot restore game data after logout',async()=>{
 const {a,context}=setup('#/1/3/snake');let resolve;
 context.fetch=()=>new Promise(r=>{resolve=r});a.loadSnake(3);a.applyState({});
 resolve({ok:true,json:async()=>({items})});await new Promise(r=>setImmediate(r));assert.equal(Object.keys(a.S.snake).length,0);
});


const items46=JSON.parse(fs.readFileSync('data/v1c4-6-snake.php','utf8').split("<<<'JSON'\n")[1].split('\nJSON\n')[0]);
test('new game contains 42 numbered tasks, 14 each from chapters 4–6',()=>{
 assert.equal(items46.length,42);assert.deepEqual(items46.map(i=>i.n),Array.from({length:42},(_,i)=>i+1));
 for(const ch of [4,5,6])assert.equal(items46.filter(i=>i.ch===ch).length,14);
 assert.equal(new Set(items46.map(i=>i.tl)).size,4);assert.equal(new Set(items46.map(i=>i.a)).size,42);
 for(const i of items46)assert.ok(i.q&&i.a&&i.note);
});
test('second revision row is between 06 and 07, with both games visible',()=>{
 const {a}=setup();const html=a.viewHome();
 assert.ok(html.indexOf('href="#/1/6"')<html.indexOf('href="#/1/6/snake"'));
 assert.ok(html.indexOf('href="#/1/6/snake"')<html.indexOf('href="#/1/7"'));
 assert.equal((html.match(/class="htsi-row htsi-snake-row"/g)||[]).length,3);
 assert.ok(html.includes('Chapters 04–06'));assert.ok(html.includes('Chapters 01–03'));
 a.S.vol=2;assert.ok(!a.viewHome().includes('THE SNAKE'));
});
test('each route renders its own title, data and independent progress',()=>{
 const {a,context}=setup('#/1/6/snake');a.S.snake={3:{items},6:{items:items46}};a.S.snakeSeen={3:{1:true},6:{2:true,3:true}};
 assert.equal(a.route().name,'snake');assert.ok(a.viewSnake().includes('Chapters 04–06'));assert.ok(a.viewSnake().includes('2 / 42 answers viewed'));
 context.location.hash='#/1/3/snake';assert.ok(a.viewSnake().includes('Chapters 01–03'));assert.ok(a.viewSnake().includes('1 / 42 answers viewed'));
});
test('reset clears only the active game',()=>{
 const {a,app,context}=setup('#/1/6/snake');a.S.snake={3:{items},6:{items:items46}};a.S.snakeSeen={3:{1:true},6:{2:true}};
 context.window.confirm=()=>true;app.querySelector=selector=>selector.includes('snake-reset')?{focus(){}}:null;a.snakeAction('snake-reset',{});
 assert.equal(Object.keys(a.S.snakeSeen[6]).length,0);assert.equal(a.S.snakeSeen[3][1],true);
});
test('second game deep link respects sign-in and Volume 1 access',()=>{
 for(const reader of [null,{volumes:[2]}]){const {a,app}=setup('#/1/6/snake',reader);a.render();assert.equal(Object.keys(a.S.snake).length,0);assert.ok(!app.innerHTML.includes('data-act="snake-open"'));}
});
test('out-of-order API responses stay assigned to the correct game',async()=>{
 const {a,context}=setup('#/1/6/snake');const pending={};context.fetch=url=>new Promise(resolve=>{pending[url]=resolve});
 a.loadSnake(3);a.loadSnake(6);
 pending['/api/snake?v=1&c=6']({ok:true,json:async()=>({items:items46})});await new Promise(r=>setImmediate(r));
 pending['/api/snake?v=1&c=3']({ok:true,json:async()=>({items})});await new Promise(r=>setImmediate(r));
 assert.equal(a.S.snake[6].items[0].ch,4);assert.equal(a.S.snake[3].items[0].ch,1);
 assert.ok(a.viewSnake().includes('Chapters 04–06'));
});

const items79=JSON.parse(fs.readFileSync('data/v1c7-9-snake.php','utf8').split("<<<'JSON'\n")[1].split('\nJSON\n')[0]);
test('third game has 42 tasks, balanced chapters and types',()=>{
 assert.deepEqual(items79.map(i=>i.n),Array.from({length:42},(_,i)=>i+1));
 for(const ch of [7,8,9])assert.equal(items79.filter(i=>i.ch===ch).length,14);
 assert.deepEqual([0,1,2,3].map(t=>items79.filter(i=>i.t===t).length),[11,11,10,10]);
 for(const i of items79)assert.ok(i.q&&i.a&&i.note);
});
test('third row is between 09 and 10 and routes to 07–09',()=>{
 const {a}=setup('#/1/9/snake');const html=a.viewHome();
 assert.ok(html.indexOf('href="#/1/9"')<html.indexOf('href="#/1/9/snake"'));
 assert.ok(html.indexOf('href="#/1/9/snake"')<html.indexOf('href="#/1/10"'));
 assert.equal(a.route().name,'snake');a.S.snake={9:{items:items79}};
 assert.ok(a.viewSnake().includes('Chapters 07–09'));
 assert.equal((a.viewSnake().match(/data-act="snake-open"/g)||[]).length,42);
});
test('third game reset preserves both earlier games',()=>{
 const {a,app,context}=setup('#/1/9/snake');a.S.snake={9:{items:items79}};
 a.S.snakeSeen={3:{1:true},6:{2:true},9:{3:true}};
 context.window.confirm=()=>true;app.querySelector=s=>s.includes('snake-reset')?{focus(){}}:null;
 a.snakeAction('snake-reset',{});
 assert.equal(Object.keys(a.S.snakeSeen[9]).length,0);assert.ok(a.S.snakeSeen[3][1]);assert.ok(a.S.snakeSeen[6][2]);
});
test('third game deep link retains access gating',()=>{
 for(const reader of [null,{volumes:[2]}]){const {a,app}=setup('#/1/9/snake',reader);a.render();assert.equal(Object.keys(a.S.snake).length,0);assert.ok(!app.innerHTML.includes('data-act="snake-open"'));}
});
test('third game requests its own API data',async()=>{
 const {a,context}=setup('#/1/9/snake');let requested;
 context.fetch=async url=>{requested=url;return {ok:true,json:async()=>({items:items79})}};
 a.loadSnake(9);await new Promise(r=>setImmediate(r));
 assert.equal(requested,'/api/snake?v=1&c=9');assert.equal(a.S.snake[9].items[0].ch,7);
 assert.ok(a.viewSnake().includes('Chapters 07–09'));
});
