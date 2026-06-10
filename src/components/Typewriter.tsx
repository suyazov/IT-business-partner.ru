import { useRef, useEffect, useState } from 'react'

interface TypewriterProps {
  text: string
  speed?: number
  delay?: number
  className?: string
  onComplete?: () => void
}

export default function Typewriter({
  text,
  speed = 80,
  delay = 0,
  className = '',
  onComplete
}: TypewriterProps) {
  const elementRef = useRef<HTMLSpanElement>(null)
  const [displayed, setDisplayed] = useState('')
  const currentCharRef = useRef(0)
  const isTagRef = useRef(false)

  useEffect(() => {
    currentCharRef.current = 0
    isTagRef.current = false
    setDisplayed('')

    const timeout = setTimeout(() => {
      const type = () => {
        const fullText = text

        if (currentCharRef.current < fullText.length) {
          const upcoming = fullText.slice(currentCharRef.current)
          let charToAdd = ''

          if (upcoming.startsWith('<')) {
            isTagRef.current = true
            charToAdd = '<'
          } else if (upcoming.startsWith('>')) {
            isTagRef.current = false
            charToAdd = '>'
          } else {
            charToAdd = fullText.charAt(currentCharRef.current)
          }

          currentCharRef.current += 1
          setDisplayed(prev => prev + charToAdd)

          if (!isTagRef.current) {
            setTimeout(() => type(), speed)
          } else {
            type()
          }
        } else {
          onComplete?.()
        }
      }

      type()
    }, delay)

    return () => clearTimeout(timeout)
  }, [text, speed, delay, onComplete])

  return (
    <span
      ref={elementRef}
      className={className}
      dangerouslySetInnerHTML={{ __html: displayed + '<span class="animate-pulse">|</span>' }}
    />
  )
}
