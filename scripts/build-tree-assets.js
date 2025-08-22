import esbuild from 'esbuild';
import fs from 'fs';
import path from 'path';

// Configuration pour le build des assets Tree avec jQuery
const buildOptions = {
    // JavaScript
    entryPoints: ['resources/js/tree/tree-component.js'],
    outfile: 'public/js/tree-component.js',
    bundle: true,
    minify: process.argv.includes('--minify'),
    sourcemap: process.argv.includes('--dev'),
    target: ['es2020'],
    format: 'iife',
    globalName: 'TreeComponent',
    // Pour jQuery, nous devons nous assurer qu'il est disponible globalement
    external: [], // Ne pas externaliser, tout inclure dans le bundle
    banner: {
        js: `// Tree Component with jQuery - Built on ${new Date().toISOString()}`
    }
};

async function buildAssets() {
    try {
        console.log('🚀 Building Tree assets...');

        // Build JavaScript
        await esbuild.build(buildOptions);
        console.log('✅ JavaScript built successfully');

        // Build CSS manuellement car nous avons plusieurs fichiers
        const cssFiles = [
            'resources/css/tree/jquery.nestable.css',
            'resources/css/tree/button.css',
            'resources/css/tree/plugin.css'
        ];

        let combinedCSS = '';
        for (const file of cssFiles) {
            if (fs.existsSync(file)) {
                const content = fs.readFileSync(file, 'utf8');
                combinedCSS += `/* ${file} */\n${content}\n\n`;
            }
        }

        // Minifier le CSS si demandé
        if (process.argv.includes('--minify')) {
            combinedCSS = combinedCSS
                .replace(/\/\*[\s\S]*?\*\//g, '')
                .replace(/\s+/g, ' ')
                .replace(/;\s*}/g, '}')
                .replace(/\s*{\s*/g, '{')
                .replace(/;\s*/g, ';')
                .trim();
        }

        fs.writeFileSync('public/css/tree-component.css', combinedCSS);
        console.log('✅ CSS built successfully');

        console.log('🎉 Tree assets build completed!');

    } catch (error) {
        console.error('❌ Build failed:', error);
        process.exit(1);
    }
}

// Mode watch pour le développement
if (process.argv.includes('--watch')) {
    console.log('👀 Watching for changes...');

    const jsContext = esbuild.context({
        ...buildOptions,
        plugins: [{
            name: 'rebuild-notify',
            setup(build) {
                build.onEnd(() => console.log('🔄 JS rebuilt'));
            }
        }]
    });

    const cssContext = esbuild.context({
        ...cssOptions,
        plugins: [{
            name: 'rebuild-notify',
            setup(build) {
                build.onEnd(() => console.log('🔄 CSS rebuilt'));
            }
        }]
    });

    Promise.all([
        jsContext.then(ctx => ctx.watch()),
        cssContext.then(ctx => ctx.watch())
    ]);
} else {
    buildAssets();
}
