#!/usr/bin/env node

const { exec } = require('child_process');
const fs = require('fs');
const path = require('path');
const util = require('util');
const execPromise = util.promisify(exec);

async function mdToVideo(mdFilePath, outputVideoPath) {
  try {
    console.log('[MARP-VIDEO] Iniciando conversión de Markdown a video');
    console.log(`[MARP-VIDEO] Archivo MD de entrada: ${mdFilePath}`);
    console.log(`[MARP-VIDEO] Archivo de video de salida: ${outputVideoPath}`);
    
    // 1. Convertir MD a imágenes usando marp-cli
    const tempDir = path.dirname(outputVideoPath);
    const imagePattern = path.join(tempDir, 'slide_%03d.png');
    
    console.log('[MARP-VIDEO] Convirtiendo Markdown a imágenes PNG');
    const marpCmd = `marp --image=png --output=${imagePattern} ${mdFilePath}`;
    console.log(`[MARP-VIDEO] Comando Marp: ${marpCmd}`);
    
    await execPromise(marpCmd);
    console.log('[MARP-VIDEO] Conversión a imágenes completada');
    
    // 2. Convertir imágenes a video usando FFmpeg
    console.log('[MARP-VIDEO] Convirtiendo imágenes a video MP4');
    const ffmpegCmd = `ffmpeg -framerate 1/3 -i ${imagePattern} -r 30 -c:v libx264 -pix_fmt yuv420p -vf "scale=1280:720" ${outputVideoPath} -y`;
    console.log(`[MARP-VIDEO] Comando FFmpeg: ${ffmpegCmd}`);
    
    await execPromise(ffmpegCmd);
    console.log(`[MARP-VIDEO] Video generado exitosamente: ${outputVideoPath}`);
  } catch (error) {
    console.error('[MARP-VIDEO-ERROR] Error en la conversión:', error);
    process.exit(1);
  }
}

// Verificar argumentos
exports.mdToVideo = mdToVideo;
