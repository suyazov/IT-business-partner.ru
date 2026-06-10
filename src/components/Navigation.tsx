import { useState, useEffect } from 'react'

export default function Navigation() {
  const [scrolled, setScrolled] = useState(false)

  useEffect(() => {
    const onScroll = () => {
      setScrolled(window.scrollY > 100)
    }
    window.addEventListener('scroll', onScroll)
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  const scrollToSection = (id: string) => {
    const element = document.getElementById(id)
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' })
    }
  }

  return (
    <nav
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-500 ${
        scrolled
          ? 'bg-[rgba(20,20,20,0.9)] backdrop-blur-md'
          : 'bg-transparent'
      }`}
      style={{ pointerEvents: 'none' }}
    >
      <div className="grid-container items-center py-6">
        <div className="col-span-2" style={{ pointerEvents: 'auto' }}>
          <button
            onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
            className="font-mono-accent text-white hover:text-[#2C2CFF] transition-colors"
          >
            АРТЁМ СУЯЗОВ
          </button>
        </div>

        <div
          className="col-span-10 flex justify-end gap-8"
          style={{ pointerEvents: 'auto' }}
        >
          <button
            onClick={() => scrollToSection('about')}
            className="font-mono-accent text-white hover:text-[#2C2CFF] transition-colors"
          >
            О СЕБЕ
          </button>
          <button
            onClick={() => scrollToSection('services')}
            className="font-mono-accent text-white hover:text-[#2C2CFF] transition-colors"
          >
            НЕЙРОРЕШЕНИЯ
          </button>
          <button
            onClick={() => scrollToSection('cases')}
            className="font-mono-accent text-white hover:text-[#2C2CFF] transition-colors"
          >
            КЕЙСЫ
          </button>
          <button
            onClick={() => scrollToSection('pricing')}
            className="font-mono-accent text-white hover:text-[#2C2CFF] transition-colors"
          >
            ТАРИФЫ
          </button>
          <button
            onClick={() => scrollToSection('faq')}
            className="font-mono-accent text-white hover:text-[#2C2CFF] transition-colors"
          >
            FAQ
          </button>
          <a
            href="/#/blog"
            className="font-mono-accent text-white hover:text-[#2C2CFF] transition-colors"
          >
            БЛОГ
          </a>
        </div>
      </div>
    </nav>
  )
}
