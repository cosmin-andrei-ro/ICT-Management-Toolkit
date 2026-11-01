// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

// Dependency graph of a configuration item (CMDB). Registered as an Alpine
// component (strict CSP, ADR 0006); Cytoscape is loaded only when a graph is shown.
const colors = { low: '#9ca3af', medium: '#60a5fa', high: '#f59e0b', critical: '#dc2626' };

export default () => ({
    cy: null,

    async init() {
        const graph = JSON.parse(this.$el.dataset.graph || '{"nodes":[],"edges":[]}');
        const { default: cytoscape } = await import('cytoscape');

        this.cy = cytoscape({
            container: this.$refs.canvas,
            elements: [
                ...graph.nodes.map((node) => ({ data: node })),
                ...graph.edges.map((edge) => ({ data: edge })),
            ],
            style: [
                {
                    selector: 'node',
                    style: {
                        label: 'data(label)',
                        'font-size': 11,
                        'text-valign': 'bottom',
                        'text-margin-y': 4,
                        width: 22,
                        height: 22,
                        'background-color': (node) => colors[node.data('criticality')] ?? colors.medium,
                    },
                },
                { selector: 'node[?current]', style: { 'border-width': 4, 'border-color': '#111827', width: 30, height: 30 } },
                {
                    selector: 'edge',
                    style: {
                        label: 'data(label)',
                        'font-size': 9,
                        color: '#6b7280',
                        width: 1.5,
                        'line-color': '#d1d5db',
                        'target-arrow-color': '#9ca3af',
                        'target-arrow-shape': 'triangle',
                        'curve-style': 'bezier',
                        'text-rotation': 'autorotate',
                    },
                },
            ],
            layout: { name: 'breadthfirst', directed: true, padding: 20, spacingFactor: 1.1 },
            wheelSensitivity: 0.3,
        });

        this.cy.on('tap', 'node', (event) => {
            const url = event.target.data('url');

            if (url && !event.target.data('current')) {
                window.location.assign(url);
            }
        });
    },

    destroy() {
        this.cy?.destroy();
    },
});
