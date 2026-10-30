document.addEventListener('DOMContentLoaded', () => {
    const bracketFlow = document.querySelector('[data-bracket-flow]');
    const bracketSvg = bracketFlow?.querySelector('.stage-connectors');

    // Dibuja una llave clásica usando las posiciones reales de cada tarjeta.
    // Al cambiar el tamaÃ±o de la pantalla, las líneas se vuelven a calcular.
    const drawBracketConnectors = () => {
        if (!bracketFlow || !bracketSvg || !bracketFlow.offsetParent) return;

        const namespace = 'http://www.w3.org/2000/svg';
        const flowBox = bracketFlow.getBoundingClientRect();
        const width = bracketFlow.scrollWidth;
        const height = bracketFlow.offsetHeight;
        const columns = [...bracketFlow.querySelectorAll('.stage-column')];
        const champion = bracketFlow.querySelector('.stage-champion');

        bracketSvg.replaceChildren();
        bracketSvg.setAttribute('viewBox', `0 0 ${width} ${height}`);

        const definitions = document.createElementNS(namespace, 'defs');
        const marker = document.createElementNS(namespace, 'marker');
        marker.setAttribute('id', 'bracket-arrowhead');
        marker.setAttribute('markerWidth', '7');
        marker.setAttribute('markerHeight', '7');
        marker.setAttribute('refX', '6');
        marker.setAttribute('refY', '3.5');
        marker.setAttribute('orient', 'auto');
        const arrow = document.createElementNS(namespace, 'path');
        arrow.setAttribute('d', 'M0,0 L7,3.5 L0,7 Z');
        arrow.setAttribute('fill', '#ffd08b');
        marker.appendChild(arrow);
        definitions.appendChild(marker);
        bracketSvg.appendChild(definitions);

        const connect = (source, target) => {
            if (!source || !target) return;
            const sourceBox = source.getBoundingClientRect();
            const targetBox = target.getBoundingClientRect();
            const startX = sourceBox.right - flowBox.left;
            const startY = sourceBox.top - flowBox.top + (sourceBox.height / 2);
            const endX = targetBox.left - flowBox.left - 5;
            const endY = targetBox.top - flowBox.top + (targetBox.height / 2);
            const middleX = startX + ((endX - startX) / 2);
            const path = document.createElementNS(namespace, 'path');

            path.setAttribute('d', `M ${startX} ${startY} H ${middleX} V ${endY} H ${endX}`);
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke', 'rgba(255, 208, 139, .72)');
            path.setAttribute('stroke-width', '2');
            path.setAttribute('stroke-linejoin', 'round');
            path.setAttribute('marker-end', 'url(#bracket-arrowhead)');
            bracketSvg.appendChild(path);
        };

        columns.forEach((column, columnIndex) => {
            const nextColumn = columns[columnIndex + 1];
            if (!nextColumn) return;
            const currentMatches = [...column.querySelectorAll('.tree-match')];
            const nextMatches = [...nextColumn.querySelectorAll('.tree-match')];

            currentMatches.forEach((match, matchIndex) => {
                const targetIndex = Math.min(nextMatches.length - 1, Math.floor(matchIndex * nextMatches.length / currentMatches.length));
                connect(match, nextMatches[targetIndex]);
            });
        });

        connect(columns.at(-1)?.querySelector('.tree-match'), champion);
    };

    const scheduleBracketDraw = () => requestAnimationFrame(() => requestAnimationFrame(drawBracketConnectors));

    if (!bracketFlow) return;
    new ResizeObserver(scheduleBracketDraw).observe(bracketFlow);
    document.addEventListener('shown.bs.tab', scheduleBracketDraw);
    window.addEventListener('resize', scheduleBracketDraw);
    scheduleBracketDraw();
});