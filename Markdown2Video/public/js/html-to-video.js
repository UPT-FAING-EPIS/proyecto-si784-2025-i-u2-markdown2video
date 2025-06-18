#!/usr/bin/env node

/**
 * Script para convertir HTML de Marp a video MP4
 * Uso: node html-to-video.js <archivo-html> <archivo-salida>
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

async function htmlToVideo(htmlFilePath, outputVideoPath) {
    let browser;
    
    try {
        console.log('Iniciando conversión HTML a video...');
        
        // Verificar que el archivo HTML existe
        if (!fs.existsSync(htmlFilePath)) {
            throw new Error(`Archivo HTML no encontrado: ${htmlFilePath}`);
        }
        
        // Lanzar navegador
        browser = await puppeteer.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--no-first-run',
                '--no-zygote',
                '--single-process'
            ],
            protocolTimeout: 60000  // Aumentar timeout a 60 segundos
        });
        
        const page = await browser.newPage();
        
        // Configurar viewport para video HD
        await page.setViewport({
            width: 1280,
            height: 720,
            deviceScaleFactor: 1
        });
        
        // Cargar el archivo HTML
        const htmlContent = fs.readFileSync(htmlFilePath, 'utf8');
        await page.setContent(htmlContent, {
            waitUntil: 'networkidle0',
            timeout: 60000  // Aumentar de 30000 a 60000
        });
        
        // Esperar a que se cargue completamente
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        // En lugar de page.waitForTimeout(500)
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // Buscar todas las diapositivas
        const slides = await page.$$('.marp-slide, section, .slide');
        
        if (slides.length === 0) {
            console.log('No se encontraron diapositivas, capturando página completa...');
            // Si no hay diapositivas específicas, capturar la página completa
            await capturePageAsVideo(page, outputVideoPath);
        } else {
            console.log(`Encontradas ${slides.length} diapositivas`);
            await captureSlidesAsVideo(page, slides, outputVideoPath);
        }
        
        console.log(`Video generado exitosamente: ${outputVideoPath}`);
        
    } catch (error) {
        console.error('Error al generar video:', error.message);
        process.exit(1);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

async function capturePageAsVideo(page, outputPath) {
    // Capturar una imagen de la página
    const screenshotPath = outputPath.replace('.mp4', '_screenshot.png');
    
    await page.screenshot({
        path: screenshotPath,
        fullPage: true,
        type: 'png'
    });
    
    // Convertir imagen a video usando FFmpeg
    const { exec } = require('child_process');
    const util = require('util');
    const execPromise = util.promisify(exec);
    
    try {
        const command = `ffmpeg -loop 1 -i "${screenshotPath}" -c:v libx264 -t 10 -pix_fmt yuv420p -vf "scale=1280:720" "${outputPath}" -y`;
        await execPromise(command);
        
        // Limpiar imagen temporal
        if (fs.existsSync(screenshotPath)) {
            fs.unlinkSync(screenshotPath);
        }
    } catch (error) {
        console.error('Error ejecutando FFmpeg:', error.message);
        throw error;
    }
}

async function captureSlidesAsVideo(page, slides, outputPath) {
    const tempDir = path.dirname(outputPath);
    const imageFiles = [];
    
    try {
        // Capturar cada diapositiva como imagen
        for (let i = 0; i < slides.length; i++) {
            const slide = slides[i];
            
            // Hacer scroll a la diapositiva
            await slide.scrollIntoView();
            await page.waitForTimeout(500);
            
            // Capturar screenshot de la diapositiva
            const imagePath = path.join(tempDir, `slide_${i.toString().padStart(3, '0')}.png`);
            
            await slide.screenshot({
                path: imagePath,
                type: 'png'
            });
            
            imageFiles.push(imagePath);
            console.log(`Capturada diapositiva ${i + 1}/${slides.length}`);
        }
        
        // Crear video desde las imágenes usando FFmpeg
        await createVideoFromImages(imageFiles, outputPath);
        
    } finally {
        // Limpiar archivos temporales
        imageFiles.forEach(file => {
            if (fs.existsSync(file)) {
                fs.unlinkSync(file);
            }
        });
    }
}

async function createVideoFromImages(imageFiles, outputPath) {
    const { exec } = require('child_process');
    const util = require('util');
    const execPromise = util.promisify(exec);
    
    // Crear archivo de lista para FFmpeg
    const listFile = outputPath.replace('.mp4', '_list.txt');
    const listContent = imageFiles.map(file => `file '${file}'\nduration 3`).join('\n') + '\n';
    
    fs.writeFileSync(listFile, listContent);
    
    try {
        // Comando FFmpeg para crear video desde imágenes
        const command = `ffmpeg -f concat -safe 0 -i "${listFile}" -vsync vfr -pix_fmt yuv420p -vf "scale=1280:720" "${outputPath}" -y`;
        
        console.log('Generando video con FFmpeg...');
        await execPromise(command);
        
    } finally {
        // Limpiar archivo de lista
        if (fs.existsSync(listFile)) {
            fs.unlinkSync(listFile);
        }
    }
}

// Verificar argumentos de línea de comandos
if (process.argv.length < 4) {
    console.error('Uso: node html-to-video.js <archivo-html> <archivo-salida>');
    process.exit(1);
}

const htmlFile = process.argv[2];
const outputFile = process.argv[3];

// Ejecutar conversión
htmlToVideo(htmlFile, outputFile)
    .then(() => {
        console.log('Conversión completada exitosamente');
        process.exit(0);
    })
    .catch((error) => {
        console.error('Error en la conversión:', error.message);
        process.exit(1);
    });