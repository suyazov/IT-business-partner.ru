import { useRef, useEffect } from 'react'
import * as THREE from 'three'
import { EffectComposer } from 'three/examples/jsm/postprocessing/EffectComposer.js'
import { RenderPass } from 'three/examples/jsm/postprocessing/RenderPass.js'
import { UnrealBloomPass } from 'three/examples/jsm/postprocessing/UnrealBloomPass.js'

const GridShaderMaterial = new THREE.ShaderMaterial({
  transparent: true,
  side: THREE.DoubleSide,
  uniforms: {
    time: { value: 0 },
    uColor: { value: new THREE.Color('#2C2CFF') }
  },
  vertexShader: `
    varying vec2 vUv;
    uniform float time;
    void main() {
      vUv = uv;
      vec3 pos = position;
      pos.z += sin(pos.x * 5.0 + time) * 0.1;
      gl_Position = projectionMatrix * modelViewMatrix * vec4(pos, 1.0);
    }
  `,
  fragmentShader: `
    varying vec2 vUv;
    uniform vec3 uColor;
    void main() {
      float grid = step(0.95, fract(vUv.x * 20.0)) + step(0.95, fract(vUv.y * 20.0));
      float glow = smoothstep(0.5, 0.0, length(vUv - 0.5));
      gl_FragColor = vec4(uColor, grid * 0.2 + glow * 0.4);
    }
  `
})

const linesMaterial = new THREE.LineBasicMaterial({
  color: 0x2C2CFF,
  transparent: true,
  opacity: 0.4
})

class Pixel extends THREE.Group {
  angle: number
  radius: number
  speed: number
  yOffset: number

  constructor() {
    super()
    this.angle = Math.random() * Math.PI * 2
    this.radius = 2 + Math.random() * 3
    this.speed = 0.005 + Math.random() * 0.01
    this.yOffset = Math.random() * 100

    const geometry = new THREE.BoxGeometry(0.05, 0.05, 0.05)
    const material = new THREE.MeshBasicMaterial({
      color: 0xffffff,
      transparent: true,
      opacity: 0.8
    })
    const box = new THREE.Mesh(geometry, material)
    this.add(box)
  }

  update(time: number, index: number) {
    this.angle += this.speed
    const r = this.radius + Math.sin(time * 2 + index) * 0.2
    this.position.x = Math.cos(this.angle) * r
    this.position.z = Math.sin(this.angle) * r
    this.position.y = Math.sin(time + this.yOffset) * 1.5
    this.rotation.x = this.rotation.y = time * 0.5 + index
  }
}

class DataCloud extends THREE.Group {
  sphere: THREE.Mesh
  lines: THREE.Line[]
  pixels: Pixel[]

  constructor() {
    super()
    const sphereGeo = new THREE.SphereGeometry(3.5, 32, 32)
    const gridMat = GridShaderMaterial.clone()
    gridMat.uniforms.uColor.value = new THREE.Color('#2C2CFF')
    this.sphere = new THREE.Mesh(sphereGeo, gridMat)
    this.add(this.sphere)

    this.lines = []
    this.pixels = []

    for (let i = 0; i < 100; i++) {
      const pixel = new Pixel()
      pixel.position.set(0, 0, 0)
      this.pixels.push(pixel)
      this.add(pixel)
    }
  }

  update(time: number) {
    ;(this.sphere.material as THREE.ShaderMaterial).uniforms.time.value = time
    this.sphere.rotation.y = time * 0.1

    // Clear old lines
    this.lines.forEach(line => {
      line.geometry.dispose()
      this.remove(line)
    })
    this.lines = []

    this.pixels.forEach((pixel, i) => {
      pixel.update(time, i)

      if (i % 5 === 0) {
        const target = this.pixels[(i + 5) % this.pixels.length]
        const points = [pixel.position.clone(), target.position.clone()]
        const geometry = new THREE.BufferGeometry().setFromPoints(points)
        const line = new THREE.Line(geometry, linesMaterial)
        this.lines.push(line)
        this.add(line)
      }
    })
  }
}

export default function DataCloudCanvas() {
  const containerRef = useRef<HTMLDivElement>(null)
  const mouseRef = useRef({ x: 0, y: 0 })

  useEffect(() => {
    const container = containerRef.current
    if (!container) return

    const scene = new THREE.Scene()
    const camera = new THREE.PerspectiveCamera(
      75,
      container.clientWidth / container.clientHeight,
      0.1,
      100
    )
    camera.position.z = 8

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true })
    renderer.setSize(container.clientWidth, container.clientHeight)
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2))
    container.appendChild(renderer.domElement)

    // Post-processing
    const composer = new EffectComposer(renderer)
    const renderPass = new RenderPass(scene, camera)
    composer.addPass(renderPass)

    const bloomPass = new UnrealBloomPass(
      new THREE.Vector2(container.clientWidth, container.clientHeight),
      1.5,
      0.4,
      0.85
    )
    bloomPass.strength = 0.6
    bloomPass.radius = 0.5
    bloomPass.threshold = 0.85
    composer.addPass(bloomPass)

    // Data Cloud
    const dataCloud = new DataCloud()
    scene.add(dataCloud)

    const clock = new THREE.Clock()

    // Mouse move
    const onMouseMove = (event: MouseEvent) => {
      mouseRef.current.x = (event.clientX / window.innerWidth) * 2 - 1
      mouseRef.current.y = -(event.clientY / window.innerHeight) * 2 + 1

      // Vertex distortion
      const positionAttribute = dataCloud.sphere.geometry.attributes.position
      const array = positionAttribute.array as Float32Array

      for (let i = 0; i < array.length; i += 3) {
        const waveX = Math.sin(array[i + 1] * 5 + mouseRef.current.x * 5) * 0.1
        const waveY = Math.cos(array[i] * 5 + mouseRef.current.y * 5) * 0.1
        array[i] += waveX
        array[i + 1] += waveY
      }
      positionAttribute.needsUpdate = true
    }
    window.addEventListener('mousemove', onMouseMove)

    // Animation loop
    let animationId: number
    const animate = () => {
      animationId = requestAnimationFrame(animate)
      const time = clock.getElapsedTime()

      // Camera follows mouse
      camera.position.x += (mouseRef.current.x * 2 - camera.position.x) * 0.05
      camera.position.y += (mouseRef.current.y * 2 - camera.position.y) * 0.05
      camera.lookAt(scene.position)

      dataCloud.update(time)
      composer.render()
    }
    animate()

    // Resize
    const onResize = () => {
      if (!container) return
      camera.aspect = container.clientWidth / container.clientHeight
      camera.updateProjectionMatrix()
      renderer.setSize(container.clientWidth, container.clientHeight)
      composer.setSize(container.clientWidth, container.clientHeight)
    }
    window.addEventListener('resize', onResize)

    return () => {
      cancelAnimationFrame(animationId)
      window.removeEventListener('mousemove', onMouseMove)
      window.removeEventListener('resize', onResize)
      renderer.dispose()
      composer.dispose()
      if (container.contains(renderer.domElement)) {
        container.removeChild(renderer.domElement)
      }
    }
  }, [])

  return (
    <div
      ref={containerRef}
      style={{
        position: 'absolute',
        top: 0,
        left: 0,
        width: '100%',
        height: '100%',
        zIndex: 0
      }}
    />
  )
}
