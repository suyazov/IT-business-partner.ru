import { useRef, useEffect } from 'react'

const marqueeText = 'TELEGRAM \u2022 AI-FIRST \u2022 ВАЙБ-КОДИНГ \u2022 IT-ПАРТНЁРСТВО \u2022 '

export default function FooterSection() {
  const marqueeRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const marquee = marqueeRef.current
    if (!marquee) return

    let animationId: number
    let position = 0

    const animate = () => {
      position -= 1
      if (position <= -marquee.scrollWidth / 2) {
        position = 0
      }
      marquee.style.transform = `translateX(${position}px)`
      animationId = requestAnimationFrame(animate)
    }

    animationId = requestAnimationFrame(animate)

    return () => cancelAnimationFrame(animationId)
  }, [])

  const scrollToSection = (id: string) => {
    if (id === 'blog') {
      window.location.href = '/#/blog'
      return
    }
    const element = document.getElementById(id)
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' })
    }
  }

  return (
    <footer className="section-dark relative overflow-hidden" style={{ paddingBottom: '0' }}>
      <div className="grid-container pb-16">
        {/* Logo & Description */}
        <div className="col-span-4">
          <div
            className="text-white font-medium mb-4"
            style={{ fontSize: '18px' }}
          >
            АРТЁМ СУЯЗОВ
          </div>
          <p
            className="text-secondary-dark"
            style={{ fontSize: '14px', lineHeight: '21px' }}
          >
            IT-бизнес-партнёр. 16 лет опыта.
            <br />
            AI-first подход. Внедряю цифровые решения,
            <br />
            которые влияют на рост бизнеса.
          </p>
        </div>

        {/* Navigation */}
        <div className="col-span-4">
          <div
            className="font-mono-accent text-secondary-dark mb-4"
            style={{ fontSize: '11px' }}
          >
            НАВИГАЦИЯ
          </div>
          <div className="space-y-2">
            {[
              { label: 'О СЕБЕ', id: 'about' },
              { label: 'НЕЙРОРЕШЕНИЯ', id: 'services' },
              { label: 'КЕЙСЫ', id: 'cases' },
              { label: 'ТАРИФЫ', id: 'pricing' },
              { label: 'FAQ', id: 'faq' },
              { label: 'БЛОГ', id: 'blog' }
            ].map((item) => (
              <button
                key={item.id}
                onClick={() => scrollToSection(item.id)}
                className="block text-white hover:text-[#2C2CFF] transition-colors"
                style={{ fontSize: '14px' }}
              >
                {item.label}
              </button>
            ))}
          </div>
        </div>

        {/* Contact */}
        <div className="col-span-4">
          <div
            className="font-mono-accent text-secondary-dark mb-4"
            style={{ fontSize: '11px' }}
          >
            КОНТАКТЫ
          </div>
          <div className="space-y-2">
            <a
              href="tel:+79064790552"
              className="block text-white hover:text-[#2C2CFF] transition-colors"
              style={{ fontSize: '14px' }}
            >
              +7 (906) 479-05-52
            </a>
            <a
              href="https://t.me/moi_imperator"
              target="_blank"
              rel="noopener noreferrer"
              className="block text-white hover:text-[#2C2CFF] transition-colors"
              style={{ fontSize: '14px' }}
            >
              @moi_imperator
            </a>
            <a
              href="mailto:artem.suyazov@gmail.com"
              className="block text-white hover:text-[#2C2CFF] transition-colors"
              style={{ fontSize: '14px' }}
            >
              artem.suyazov@gmail.com
            </a>
          </div>
        </div>

        {/* Copyright */}
        <div className="col-span-12 mt-16 pt-8" style={{ borderTop: '1px solid #2A2A2A' }}>
          <p className="text-secondary-dark" style={{ fontSize: '12px' }}>
            &copy; 2025 Артём Суязов. Все права защищены.
          </p>
        </div>
      </div>

      {/* Marquee */}
      <div
        className="relative overflow-hidden w-full"
        style={{ height: '120px', opacity: 0.1 }}
      >
        <div
          ref={marqueeRef}
          className="absolute whitespace-nowrap"
          style={{
            fontSize: '84px',
            fontWeight: 500,
            color: '#FFFFFF',
            fontFamily: "'Inter', sans-serif",
            letterSpacing: '-0.04em',
            lineHeight: '120px'
          }}
        >
          {marqueeText.repeat(6)}
          {marqueeText.repeat(6)}
        </div>
      </div>
    </footer>
  )
}
