async function x(){if(window.mermaid)return window.mermaid;let c=document.createElement("script");return c.src="https://cdn.jsdelivr.net/npm/mermaid@10.6.1/dist/mermaid.min.js",new Promise((m,g)=>{c.onload=()=>{window.mermaid.initialize({startOnLoad:!1,theme:"default",flowchart:{useMaxWidth:!0,htmlLabels:!0},securityLevel:"loose"}),m(window.mermaid)},c.onerror=g,document.head.appendChild(c)})}function f({apiEndpoint:c,diagramId:m,height:g="400px",theme:p="default",type:u="flowchart",direction:y="LR"}){return{loading:!0,error:null,diagramData:null,mermaidInstance:null,apiEndpoint:c,diagramId:m,height:g,theme:p,type:u,direction:y,async init(){try{this.mermaidInstance=await x(),this.theme!=="default"&&this.mermaidInstance.initialize({startOnLoad:!1,theme:this.theme,flowchart:{useMaxWidth:!0,htmlLabels:!0},securityLevel:"loose"}),await this.loadDiagramData(),await this.renderDiagram()}catch(t){console.error("Error initializing Mermaid diagram:",t),this.error="Erreur lors du chargement du diagramme",this.loading=!1}},async loadDiagramData(){if(!this.apiEndpoint)throw new Error("API endpoint not provided");let t=await fetch(this.apiEndpoint);if(!t.ok)throw new Error(`HTTP error! status: ${t.status}`);let a=await t.json();console.log("API Response:",a),this.diagramData=a.success?a.data:a,console.log("Diagram Data:",this.diagramData)},async renderDiagram(){if(!this.diagramData||!this.mermaidInstance){console.log("Missing data or mermaid instance:",{diagramData:this.diagramData,mermaidInstance:this.mermaidInstance});return}let t=document.getElementById(this.diagramId);if(!t)throw console.error("Diagram container not found with ID:",this.diagramId),new Error("Diagram container not found");console.log("Found diagram container:",t);let a=this.generateMermaidSyntax(this.diagramData);try{t.innerHTML="",console.log("Rendering with Mermaid syntax:",a);let{svg:e}=await this.mermaidInstance.render(`${this.diagramId}-svg`,a);console.log("Mermaid rendered successfully, SVG length:",e.length),t.innerHTML=e,this.loading=!1}catch(e){throw console.error("Mermaid render error:",e),new Error("Erreur de rendu du diagramme")}},generateMermaidSyntax(t){t=t||{};let a=Array.isArray(t.nodes)?t.nodes:[],e=Array.isArray(t.edges)?t.edges:[];console.log("Generating Mermaid for nodes:",a),console.log("Generating Mermaid for edges:",e);let s=this.type!=="flowchart"?this.type:t.type||"flowchart",d=this.direction!=="LR"?this.direction:t.direction||"LR";console.log("Using type:",s,"direction:",d);let n=`${s} ${d}
`;return t.metadata&&(n+=`%% Generated for ${t.metadata.model} - ${t.metadata.total_states} states, ${t.metadata.total_transitions} transitions
`,n+=`%% Generated at ${t.metadata.generated_at}

`),a.forEach(i=>{let o=i.id||`n_${Math.random().toString(36).slice(2,8)}`,r=this.createRichNodeContent(i);n+=`    ${o}[${r}]
`}),n+=`
`,e.forEach(i=>{let o=i.from||i.source||"",r=i.to||i.target||"";if(!o||!r)return;let l="-->";i.style==="thick"?l="==>":i.style==="dotted"&&(l="-.->");let h=this.createRichEdgeContent(i);h?n+=`    ${o} ${l}|${h}| ${r}
`:n+=`    ${o} ${l} ${r}
`}),n+=`
`,a.forEach(i=>{let o=i.id||null;o&&i.color&&(n+=`    %% Style for ${o} (${i.label||o})
`,n+=`    style ${o} fill:${i.color}
`)}),n+=`
%% Click events and tooltips
`,a.forEach(i=>{let o=i.id||null;if(o&&(i.description||i.icon)){let r=[i.description||"",i.icon?`Icon: ${i.icon}`:"",i.color?`Color: ${i.color}`:""].filter(Boolean).join(" | ");r&&(n+=`    click ${o} callback "${r}"
`)}}),console.log("Generated Mermaid syntax:",n),n},escapeLabel(t){return t==null?'""':`"${String(t).replace(/"/g,'\\"')}"`},createRichNodeContent(t){let a=t.label||t.id||"\xC9tat",e=t.description||"",s=[];if(s.push(a),e&&e.length>0){let n=e.replace(/\(Couleur:.*?\)/g,"").replace(/\(Icône:.*?\)/g,"").trim();if(n&&n!==a)if(n.length>30){let o=n.split(" "),r="";o.forEach(l=>{(r+" "+l).length>30?r?(s.push(r),r=l):s.push(l):r=r?r+" "+l:l}),r&&s.push(r)}else s.push(n)}return`"${s.join("<br/>")}"`},createRichEdgeContent(t){let a=t.label||"",e=t.description||"";if(!a&&!e)return null;let s=[];if(a&&s.push(a),e&&e!==a){let n=e.replace(/\(Avec redirection\).*/,"").replace(/\(Avec formulaire\).*/," - Avec formulaire").replace(/\[Champs:\s*([^\]]+)\]/," - Champs: $1").replace(/\[URL:.*?\]/," - Redirection").trim();if(n&&n!==a)if(n.length>25){let o=n.split(" "),r="";o.forEach(l=>{(r+" "+l).length>25?r?(s.push(r),r=l):s.push(l):r=r?r+" "+l:l}),r&&s.push(r)}else s.push(n)}return`"${s.join("<br/>")}"`},async refresh(){this.loading=!0,this.error=null;try{await this.loadDiagramData(),await this.renderDiagram()}catch(t){console.error("Error refreshing diagram:",t),this.error="Erreur lors du rafra\xEEchissement",this.loading=!1}},getMetadataHtml(){if(!this.diagramData?.metadata)return"";let t=this.diagramData.metadata,a=`
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex justify-between items-center mb-3">
                        <h4 class="font-semibold text-gray-900 dark:text-gray-100">Informations du diagramme</h4>
                        <span class="text-xs text-gray-500">${new Date(t.generated_at).toLocaleString("fr-FR")}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div><strong>Mod\xE8le:</strong> ${t.model}</div>
                        <div><strong>\xC9tats:</strong> ${t.total_states}</div>
                        <div><strong>Transitions:</strong> ${t.total_transitions}</div>
                    </div>
            `;return this.diagramData?.nodes?.length>0&&(a+=`
                    <div class="mb-4">
                        <h5 class="font-medium text-gray-800 dark:text-gray-200 mb-2">\xC9tats disponibles:</h5>
                        <div class="space-y-2">
                `,this.diagramData.nodes.forEach(e=>{let s=e.color?`style="border-left: 4px solid ${e.color};"`:"";a+=`
                        <div class="pl-3 py-1 bg-white dark:bg-gray-700 rounded border-l-4" ${s}>
                            <div class="flex items-center gap-2">
                                <strong class="text-gray-900 dark:text-gray-100">${e.label||e.id}</strong>
                                ${e.icon?`<span class="text-xs text-gray-500">(${e.icon})</span>`:""}
                            </div>
                            ${e.description?`<div class="text-xs text-gray-600 dark:text-gray-400 mt-1">${e.description}</div>`:""}
                        </div>
                    `}),a+=`
                        </div>
                    </div>
                `),this.diagramData?.edges?.length>0&&(a+=`
                    <div>
                        <h5 class="font-medium text-gray-800 dark:text-gray-200 mb-2">Transitions disponibles:</h5>
                        <div class="space-y-2">
                `,this.diagramData.edges.forEach(e=>{let s=e.style==="thick"?"font-semibold":e.style==="dotted"?"italic":"";a+=`
                        <div class="pl-3 py-1 bg-white dark:bg-gray-700 rounded border-l-4 border-blue-400">
                            <div class="flex items-center gap-2 ${s}">
                                <span class="text-gray-700 dark:text-gray-300">${e.from}</span>
                                <span class="text-gray-500">\u2192</span>
                                <span class="text-gray-700 dark:text-gray-300">${e.to}</span>
                                ${e.label?`<span class="text-sm text-blue-600 dark:text-blue-400">"${e.label}"</span>`:""}
                                ${e.style?`<span class="text-xs px-2 py-1 bg-gray-200 dark:bg-gray-600 rounded">${e.style}</span>`:""}
                            </div>
                            ${e.description?`<div class="text-xs text-gray-600 dark:text-gray-400 mt-1">${e.description}</div>`:""}
                        </div>
                    `}),a+=`
                        </div>
                    </div>
                `),a+="</div>",a}}}typeof window<"u"&&(window.mermaidDiagramComponent=f);var $=f;export{$ as default};
