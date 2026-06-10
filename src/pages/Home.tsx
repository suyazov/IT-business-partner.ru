import Navigation from '../components/Navigation'
import CustomCursor from '../components/CustomCursor'
import HeroSection from '../sections/HeroSection'
import AboutSection from '../sections/AboutSection'
import ServicesSection from '../sections/ServicesSection'
import CasesSection from '../sections/CasesSection'
import ProcessSection from '../sections/ProcessSection'
import PricingSection from '../sections/PricingSection'
import FAQSection from '../sections/FAQSection'
import FooterSection from '../sections/FooterSection'

export default function Home() {
  return (
    <div className="relative">
      <CustomCursor />
      <Navigation />

      <main>
        <HeroSection />
        <AboutSection />
        <ServicesSection />
        <CasesSection />
        <ProcessSection />
        <PricingSection />
        <FAQSection />
      </main>

      <FooterSection />
    </div>
  )
}
