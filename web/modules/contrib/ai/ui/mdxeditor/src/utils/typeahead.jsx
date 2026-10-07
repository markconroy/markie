// Renders typeahead nodes in the editor without duplicated trigger. The help
// text of the matching token/variable is exposed as a native title tooltip, so
// hovering an inserted element explains what it resolves to. The descriptor is
// the config object handed to typeaheadPlugin(), so its `values` is the same
// list the autocomplete menu was built from.
export function TypeaheadEditor({ node, descriptor }) {
  const content = node.getContent()
  const item = descriptor?.values?.find((value) => value.value === content)
  const help = [item?.displayValue, item?.description].filter(Boolean).join(' - ')

  return <span title={help || undefined}>{content}</span>
}

export function TypeaheadMenuItem({ item }) {
  return (
    <div className='typeahead-item'>
      <span className="typeahead-item-title">{item.displayValue}</span>
      <span className="typeahead-item-description">{item.description}</span>
    </div>
  )
}
