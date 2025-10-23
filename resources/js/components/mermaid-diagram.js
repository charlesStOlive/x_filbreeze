// Import Mermaid from CDN dynamically
async function loadMermaid() {
    if (window.mermaid) {
        return window.mermaid;
    }

    // Load Mermaid from CDN
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/mermaid@10.6.1/dist/mermaid.min.js';
    
    return new Promise((resolve, reject) => {
        script.onload = () => {
            // Initialize Mermaid
            window.mermaid.initialize({
                startOnLoad: false,
                theme: 'default',
                flowchart: {
                    useMaxWidth: true,
                    htmlLabels: true
                },
                securityLevel: 'loose'
            });
            resolve(window.mermaid);
        };
        script.onerror = reject;
        document.head.appendChild(script);
    });
}
function mermaidDiagramComponent({
    apiEndpoint,
    diagramId,
    height = '400px',
    theme = 'default',
    type = 'flowchart',
    direction = 'LR'
}) {
    return {
        loading: true,
        error: null,
        diagramData: null,
        mermaidInstance: null,
        apiEndpoint: apiEndpoint,
        diagramId: diagramId,
        height: height,
        theme: theme,
        type: type,
        direction: direction,

        async init() {
            try {
                // Load Mermaid library
                this.mermaidInstance = await loadMermaid();

                // Update theme if different from default
                if (this.theme !== 'default') {
                    this.mermaidInstance.initialize({
                        startOnLoad: false,
                        theme: this.theme,
                        flowchart: {
                            useMaxWidth: true,
                            htmlLabels: true
                        },
                        securityLevel: 'loose'
                    });
                }

                // Load diagram data
                await this.loadDiagramData();

                // Render diagram
                await this.renderDiagram();

            } catch (error) {
                console.error('Error initializing Mermaid diagram:', error);
                this.error = 'Erreur lors du chargement du diagramme';
                this.loading = false;
            }
        },

        async loadDiagramData() {
            if (!this.apiEndpoint) {
                throw new Error('API endpoint not provided');
            }

            const response = await fetch(this.apiEndpoint);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const jsonResponse = await response.json();
            console.log('API Response:', jsonResponse);
            
            // Extract data from the API response structure
            this.diagramData = jsonResponse.success ? jsonResponse.data : jsonResponse;
            console.log('Diagram Data:', this.diagramData);
        },

        async renderDiagram() {
            if (!this.diagramData || !this.mermaidInstance) {
                console.log('Missing data or mermaid instance:', { diagramData: this.diagramData, mermaidInstance: this.mermaidInstance });
                return;
            }

            const diagramContainer = document.getElementById(this.diagramId);
            if (!diagramContainer) {
                console.error('Diagram container not found with ID:', this.diagramId);
                throw new Error('Diagram container not found');
            }

            console.log('Found diagram container:', diagramContainer);

            // Generate Mermaid syntax from JSON data
            const mermaidSyntax = this.generateMermaidSyntax(this.diagramData);

            try {
                // Clear container
                diagramContainer.innerHTML = '';

                console.log('Rendering with Mermaid syntax:', mermaidSyntax);

                // Render diagram
                const { svg } = await this.mermaidInstance.render(
                    `${this.diagramId}-svg`,
                    mermaidSyntax
                );

                console.log('Mermaid rendered successfully, SVG length:', svg.length);
                diagramContainer.innerHTML = svg;
                this.loading = false;

            } catch (error) {
                console.error('Mermaid render error:', error);
                throw new Error('Erreur de rendu du diagramme');
            }
        },

        generateMermaidSyntax(data) {
            // Defensive defaults
            data = data || {};
            const nodes = Array.isArray(data.nodes) ? data.nodes : [];
            const edges = Array.isArray(data.edges) ? data.edges : [];

            console.log('Generating Mermaid for nodes:', nodes);
            console.log('Generating Mermaid for edges:', edges);

            // Use component settings to override API data if different from defaults
            const finalType = this.type !== 'flowchart' ? this.type : (data.type || 'flowchart');
            const finalDirection = this.direction !== 'LR' ? this.direction : (data.direction || 'LR');
            
            console.log('Using type:', finalType, 'direction:', finalDirection);

            let mermaid = `${finalType} ${finalDirection}\n`;
            
            // Add header comment with metadata
            if (data.metadata) {
                mermaid += `%% Generated for ${data.metadata.model} - ${data.metadata.total_states} states, ${data.metadata.total_transitions} transitions\n`;
                mermaid += `%% Generated at ${data.metadata.generated_at}\n\n`;
            }

            // Add nodes with rich HTML content
            nodes.forEach(node => {
                const nodeId = node.id || `n_${Math.random().toString(36).slice(2,8)}`;
                
                // Create rich node content
                let nodeContent = this.createRichNodeContent(node);
                
                mermaid += `    ${nodeId}[${nodeContent}]\n`;
            });

            mermaid += '\n';

            // Add edges with rich content
            edges.forEach(edge => {
                const from = edge.from || edge.source || '';
                const to = edge.to || edge.target || '';
                if (!from || !to) return; // skip invalid edges

                let edgeStyle = '-->';
                if (edge.style === 'thick') {
                    edgeStyle = '==>';
                } else if (edge.style === 'dotted') {
                    edgeStyle = '-.->';
                }

                // Create rich edge label
                const richLabel = this.createRichEdgeContent(edge);
                
                if (richLabel) {
                    mermaid += `    ${from} ${edgeStyle}|${richLabel}| ${to}\n`;
                } else {
                    mermaid += `    ${from} ${edgeStyle} ${to}\n`;
                }
            });

            mermaid += '\n';

            // Add colors with comments
            nodes.forEach(node => {
                const nodeId = node.id || null;
                if (nodeId && node.color) {
                    mermaid += `    %% Style for ${nodeId} (${node.label || nodeId})\n`;
                    mermaid += `    style ${nodeId} fill:${node.color}\n`;
                }
            });

            // Add click events for nodes with additional info
            mermaid += '\n%% Click events and tooltips\n';
            nodes.forEach(node => {
                const nodeId = node.id || null;
                if (nodeId && (node.description || node.icon)) {
                    const tooltip = [
                        node.description || '',
                        node.icon ? `Icon: ${node.icon}` : '',
                        node.color ? `Color: ${node.color}` : ''
                    ].filter(Boolean).join(' | ');
                    
                    if (tooltip) {
                        mermaid += `    click ${nodeId} callback "${tooltip}"\n`;
                    }
                }
            });

            console.log('Generated Mermaid syntax:', mermaid);
            return mermaid;
        },

        escapeLabel(label) {
            if (label === null || label === undefined) return '""';
            return `"${String(label).replace(/"/g, '\\"')}"`;
        },

        createRichNodeContent(node) {
            const label = node.label || node.id || 'État';
            const description = node.description || '';
            
            // Create simple multi-line content for Mermaid
            let lines = [];
            
            // Main label (first line)
            lines.push(label);
            
            // Add description if available (clean it up)
            if (description && description.length > 0) {
                // Extract key info from description
                const cleanDesc = description
                    .replace(/\(Couleur:.*?\)/g, '') // Remove color info
                    .replace(/\(Icône:.*?\)/g, '')   // Remove icon info
                    .trim();
                
                if (cleanDesc && cleanDesc !== label) {
                    // Split long descriptions into multiple lines
                    const maxLineLength = 30;
                    if (cleanDesc.length > maxLineLength) {
                        const words = cleanDesc.split(' ');
                        let currentLine = '';
                        
                        words.forEach(word => {
                            if ((currentLine + ' ' + word).length > maxLineLength) {
                                if (currentLine) {
                                    lines.push(currentLine);
                                    currentLine = word;
                                } else {
                                    lines.push(word);
                                }
                            } else {
                                currentLine = currentLine ? currentLine + ' ' + word : word;
                            }
                        });
                        
                        if (currentLine) {
                            lines.push(currentLine);
                        }
                    } else {
                        lines.push(cleanDesc);
                    }
                }
            }
            
            // Join with line breaks
            const content = lines.join('<br/>');
            
            return `"${content}"`;
        },

        createRichEdgeContent(edge) {
            const label = edge.label || '';
            const description = edge.description || '';
            
            if (!label && !description) return null;
            
            let lines = [];
            
            // Main label (first line)
            if (label) {
                lines.push(label);
            }
            
            // Additional info from description
            if (description && description !== label) {
                // Clean and simplify description
                let cleanDesc = description
                    .replace(/\(Avec redirection\).*/, '') // Remove redirection details
                    .replace(/\(Avec formulaire\).*/, ' - Avec formulaire') // Simplify form info
                    .replace(/\[Champs:\s*([^\]]+)\]/, ' - Champs: $1') // Format fields
                    .replace(/\[URL:.*?\]/, ' - Redirection') // Simplify URL
                    .trim();
                
                if (cleanDesc && cleanDesc !== label) {
                    // Split long descriptions into multiple lines
                    const maxLineLength = 25;
                    if (cleanDesc.length > maxLineLength) {
                        const words = cleanDesc.split(' ');
                        let currentLine = '';
                        
                        words.forEach(word => {
                            if ((currentLine + ' ' + word).length > maxLineLength) {
                                if (currentLine) {
                                    lines.push(currentLine);
                                    currentLine = word;
                                } else {
                                    lines.push(word);
                                }
                            } else {
                                currentLine = currentLine ? currentLine + ' ' + word : word;
                            }
                        });
                        
                        if (currentLine) {
                            lines.push(currentLine);
                        }
                    } else {
                        lines.push(cleanDesc);
                    }
                }
            }
            
            // Join with line breaks if multiple lines
            const content = lines.join('<br/>');
            
            return `"${content}"`;
        },

        async refresh() {
            this.loading = true;
            this.error = null;

            try {
                await this.loadDiagramData();
                await this.renderDiagram();
            } catch (error) {
                console.error('Error refreshing diagram:', error);
                this.error = 'Erreur lors du rafraîchissement';
                this.loading = false;
            }
        },

        getMetadataHtml() {
            if (!this.diagramData?.metadata) {
                return '';
            }

            const meta = this.diagramData.metadata;
            let html = `
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex justify-between items-center mb-3">
                        <h4 class="font-semibold text-gray-900 dark:text-gray-100">Informations du diagramme</h4>
                        <span class="text-xs text-gray-500">${new Date(meta.generated_at).toLocaleString('fr-FR')}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div><strong>Modèle:</strong> ${meta.model}</div>
                        <div><strong>États:</strong> ${meta.total_states}</div>
                        <div><strong>Transitions:</strong> ${meta.total_transitions}</div>
                    </div>
            `;

            // Add nodes details
            if (this.diagramData?.nodes?.length > 0) {
                html += `
                    <div class="mb-4">
                        <h5 class="font-medium text-gray-800 dark:text-gray-200 mb-2">États disponibles:</h5>
                        <div class="space-y-2">
                `;
                
                this.diagramData.nodes.forEach(node => {
                    const colorStyle = node.color ? `style="border-left: 4px solid ${node.color};"` : '';
                    html += `
                        <div class="pl-3 py-1 bg-white dark:bg-gray-700 rounded border-l-4" ${colorStyle}>
                            <div class="flex items-center gap-2">
                                <strong class="text-gray-900 dark:text-gray-100">${node.label || node.id}</strong>
                                ${node.icon ? `<span class="text-xs text-gray-500">(${node.icon})</span>` : ''}
                            </div>
                            ${node.description ? `<div class="text-xs text-gray-600 dark:text-gray-400 mt-1">${node.description}</div>` : ''}
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }

            // Add edges details
            if (this.diagramData?.edges?.length > 0) {
                html += `
                    <div>
                        <h5 class="font-medium text-gray-800 dark:text-gray-200 mb-2">Transitions disponibles:</h5>
                        <div class="space-y-2">
                `;
                
                this.diagramData.edges.forEach(edge => {
                    const styleClass = edge.style === 'thick' ? 'font-semibold' : edge.style === 'dotted' ? 'italic' : '';
                    html += `
                        <div class="pl-3 py-1 bg-white dark:bg-gray-700 rounded border-l-4 border-blue-400">
                            <div class="flex items-center gap-2 ${styleClass}">
                                <span class="text-gray-700 dark:text-gray-300">${edge.from}</span>
                                <span class="text-gray-500">→</span>
                                <span class="text-gray-700 dark:text-gray-300">${edge.to}</span>
                                ${edge.label ? `<span class="text-sm text-blue-600 dark:text-blue-400">"${edge.label}"</span>` : ''}
                                ${edge.style ? `<span class="text-xs px-2 py-1 bg-gray-200 dark:bg-gray-600 rounded">${edge.style}</span>` : ''}
                            </div>
                            ${edge.description ? `<div class="text-xs text-gray-600 dark:text-gray-400 mt-1">${edge.description}</div>` : ''}
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }

            html += `</div>`;
            return html;
        }
    }
}

// Expose factory globally so Alpine's x-data can find it when evaluated
if (typeof window !== 'undefined') {
    window.mermaidDiagramComponent = mermaidDiagramComponent;
}

export default mermaidDiagramComponent;