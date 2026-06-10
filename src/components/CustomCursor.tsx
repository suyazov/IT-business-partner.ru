import { useRef, useEffect } from 'react'

export default function CustomCursor() {
  const cursorRef = useRef<HTMLDivElement>(null)
  const posRef = useRef({ x: 0, y: 0 })
  const targetRef = useRef({ x: 0, y: 0 })
  const isHoveringRef = useRef(false)

  useEffect(() => {
    const cursor = cursorRef.current
    if (!cursor) return

    // Check for touch device
    if ('ontouchstart' in window) {
      cursor.style.display = 'none'
      return
    }

    const onMouseMove = (e: MouseEvent) => {
      targetRef.current.x = e.clientX
      targetRef.current.y = e.clientY
    }

    const onMouseOver = (e: MouseEvent) => {
      const target = e.target as HTMLElement
      if (
        target.tagName === 'A' ||
        target.tagName === 'BUTTON' ||
        target.closest('a') ||
        target.closest('button')
      ) {
        isHoveringRef.current = true
      }
    }

    const onMouseOut = (e: MouseEvent) => {
      const target = e.target as HTMLElement
      if (
        target.tagName === 'A' ||
        target.tagName === 'BUTTON' ||
        target.closest('a') ||
        target.closest('button')
      ) {
        isHoveringRef.current = false
      }
    }

    window.addEventListener('mousemove', onMouseMove)
    document.addEventListener('mouseover', onMouseOver)
    document.addEventListener('mouseout', onMouseOut)

    let animationId: number
    const animate = () => {
      animationId = requestAnimationFrame(animate)

      posRef.current.x += (targetRef.current.x - posRef.current.x) * 0.15
      posRef.current.y += (targetRef.current.y - posRef.current.y) * 0.15

      const scale = isHoveringRef.current ? 3 : 1
      const borderRadius = isHoveringRef.current ? '50%' : '0%'

      cursor.style.transform = `translate(${posRef.current.x - 3}px, ${posRef.current.y - 3}px) scale(${scale})`
      cursor.style.borderRadius = borderRadius
    }
    animate()

    return () => {
      cancelAnimationFrame(animationId)
      window.removeEventListener('mousemove', onMouseMove)
      document.removeEventListener('mouseover', onMouseOver)
      document.removeEventListener('mouseout', onMouseOut)
    }
  }, [])

  return (
    <div
      ref={cursorRef}
      style={{
        position: 'fixed',
        top: 0,
        left: 0,
        width: 6,
        height: 6,
        backgroundColor: '#ffffff',
        mixBlendMode: 'difference',
        pointerEvents: 'none',
        zIndex: 9999,
        transition: 'border-radius 0.3s ease',
      }}
    />
  )
}
