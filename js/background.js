const canvas = document.getElementById('bgCanvas');
const ctx = canvas.getContext('2d');
const trailCanvas = document.getElementById('trailCanvas');
const trailCtx = trailCanvas.getContext('2d');

let hexes = [];
const hexRadius = 40;
const horizDist = Math.sqrt(3) * hexRadius;
const vertDist = 1.5 * hexRadius;
export const mouse = { x: -1000, y: -1000 };
let currentHue = 260;

class Hexagon {
    constructor(x, y) {
        this.x = x;
        this.y = y;
        this.glow = 0;
        this.hueOffset = Math.random() * 20;
    }

    draw() {
        const dx = this.x - mouse.x;
        const dy = this.y - mouse.y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        const interactionRadius = 250;

        if (dist < interactionRadius) {
            const intensity = Math.pow(1 - (dist / interactionRadius), 2);
            this.glow += (intensity - this.glow) * 0.15;
        } else {
            this.glow -= 0.02;
        }
        this.glow = Math.max(0, Math.min(1, this.glow));

        if (this.glow < 0.01) return;

        ctx.beginPath();
        for (let i = 0; i < 6; i++) {
            const angle = (Math.PI / 3) * i + Math.PI / 6;
            const px = this.x + (hexRadius - 2) * Math.cos(angle);
            const py = this.y + (hexRadius - 2) * Math.sin(angle);
            if (i === 0) ctx.moveTo(px, py);
            else ctx.lineTo(px, py);
        }
        ctx.closePath();

        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const hue = currentHue + this.hueOffset;
        
        if (this.glow > 0.05) {
            ctx.strokeStyle = `hsla(${hue}, 100%, 60%, ${0.2 + this.glow * 0.5})`;
            ctx.fillStyle = `hsla(${hue}, 100%, 60%, ${this.glow * 0.15})`;
            ctx.shadowColor = `hsl(${hue}, 100%, 50%)`;
            ctx.shadowBlur = 20 * this.glow;
            ctx.lineWidth = 1 + this.glow * 2;
            ctx.fill();
        } else {
            ctx.strokeStyle = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.03)';
            ctx.lineWidth = 1;
        }
        ctx.stroke();
    }
}

class Particle {
    constructor(x, y) {
        this.x = x + (Math.random() - 0.5) * 20;
        this.y = y + (Math.random() - 0.5) * 20;
        this.size = Math.random() * 2.5 + 1;
        this.life = 1;
        this.decay = Math.random() * 0.02 + 0.015;
        this.color = Math.random() > 0.4 ? '#ffcc00' : '#ffffff';
        this.speedY = Math.random() * 0.5 + 0.2;
    }

    update() {
        this.life -= this.decay;
        this.y += this.speedY;
    }

    draw() {
        trailCtx.beginPath();
        trailCtx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        trailCtx.fillStyle = this.color;
        trailCtx.globalAlpha = Math.max(0, this.life);
        if (this.life > 0.5) {
            trailCtx.shadowColor = this.color;
            trailCtx.shadowBlur = 8;
        } else {
            trailCtx.shadowBlur = 0;
        }
        trailCtx.fill();
        trailCtx.globalAlpha = 1.0;
    }
}

let particles = [];

export function initBackground() {
    resize();
    window.addEventListener('resize', resize);
    window.addEventListener('mousemove', e => {
        mouse.x = e.clientX;
        mouse.y = e.clientY;
        for(let i=0; i<2; i++) particles.push(new Particle(e.clientX, e.clientY));
    });
    animate();
}

function resize() {
    canvas.width = trailCanvas.width = window.innerWidth;
    canvas.height = trailCanvas.height = window.innerHeight;
    hexes = [];
    const cols = Math.ceil(canvas.width / horizDist) + 2;
    const rows = Math.ceil(canvas.height / vertDist) + 2;

    for (let row = -1; row < rows; row++) {
        for (let col = -1; col < cols; col++) {
            let x = col * horizDist;
            let y = row * vertDist;
            if (row % 2 !== 0) x += horizDist / 2;
            hexes.push(new Hexagon(x, y));
        }
    }
}

function animate() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    trailCtx.clearRect(0, 0, trailCanvas.width, trailCanvas.height);
    
    hexes.forEach(h => h.draw());

    for (let i = particles.length - 1; i >= 0; i--) {
        particles[i].update();
        if (particles[i].life <= 0) {
            particles.splice(i, 1);
        } else {
            particles[i].draw();
        }
    }

    requestAnimationFrame(animate);
}


export function setHue(hue) {
    currentHue = hue;
}
