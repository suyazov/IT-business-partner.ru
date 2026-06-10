import { useRef, useEffect } from 'react'

const steps = [
  {
    number: '01',
    title: 'АУДИТ',
    duration: '1-2 дня',
    description:
      'Разбор ваших процессов, выявление узких мест, формирование гипотез с оценкой ROI. AI-анализ данных и быстрая диагностика.'
  },
  {
    number: '02',
    title: 'СТРАТЕГИЯ',
    duration: '3-5 дней',
    description:
      'Целевая архитектура процессов, дорожная карта внедрения, выбор технологий. AI-first подход к проектированию решений.'
  },
  {
    number: '03',
    title: 'ВНЕДРЕНИЕ',
    duration: '7-14 дней',
    description:
      'Разработка MVP, интеграция с существующими системами, запуск на реальных данных. Вайб-кодинг для скорости.'
  },
  {
    number: '04',
    title: 'РАЗВИТИЕ',
    duration: 'Постоянно',
    description:
      'Мониторинг метрик, доработки, масштабирование. Работаю как внешний IT-директор — долгосрочное партнёрство.'
  }
]

export default function ProcessSection() {
  const sectionRef = useRef<HTMLElement>(null)
  const cardsRef = useRef<(HTMLDivElement | null)[]>([])

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            cardsRef.current.forEach((card, i) => {
              if (card) {
                setTimeout(() => {
                  card.style.opacity = '1'
                  card.style.transform = 'scale(1)'
                }, i * 150)
              }
            })
            observer.unobserve(entry.target)
          }
        })
      },
      { threshold: 0.2 }
    )

    if (sectionRef.current) {
      observer.observe(sectionRef.current)
    }

    return () => observer.disconnect()
  }, [])

  return (
    <section
      id="process"
      ref={sectionRef}
      className="section-light relative overflow-hidden"
    >
      <div className="grid-container">
        {/* Section Header */}
        <div className="col-span-12 mb-16">
          <h2
            className="font-mono-accent text-black"
            style={{ fontSize: '12px' }}
          >
            (04) ПРОЦЕСС РАБОТЫ
          </h2>
        </div>

        {/* Steps */}
        {steps.map((step, index) => (
          <div
            key={step.number}
            ref={(el) => { cardsRef.current[index] = el }}
            className="col-span-3"
            style={{
              opacity: 0,
              transform: 'scale(0.95)',
              transition: 'all 0.6s cubic-bezier(0.16, 1, 0.3, 1)'
            }}
          >
            <div
              className="p-6 h-full"
              style={{
                borderLeft: '1px solid #EAEAEA'
              }}
            >
              {/* Number */}
              <div
                className="font-mono-accent text-secondary-light mb-4"
                style={{ fontSize: '12px' }}
              >
                ({step.number})
              </div>

              {/* Title */}
              <h3
                className="text-black font-medium mb-2"
                style={{
                  fontSize: '24px',
                  lineHeight: '26px',
                  letterSpacing: '-0.24px'
                }}
              >
                {step.title}
              </h3>

              {/* Duration */}
              <div
                className="font-mono-accent text-[#2C2CFF] mb-4"
                style={{ fontSize: '11px' }}
              >
                {step.duration}
              </div>

              {/* Description */}
              <p
                className="text-secondary-light"
                style={{ fontSize: '14px', lineHeight: '21px' }}
              >
                {step.description}
              </p>
            </div>
          </div>
        ))}
      </div>
    </section>
  )
}
